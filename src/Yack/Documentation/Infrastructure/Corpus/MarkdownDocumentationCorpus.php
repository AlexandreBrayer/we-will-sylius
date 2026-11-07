<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Infrastructure\Corpus;

use App\Yack\Documentation\Domain\DocumentationCorpus;
use App\Yack\Documentation\Domain\Notice;
use App\Yack\Documentation\Domain\Passage;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter: the FICTIF demonstration notices, Markdown files `data/yack/demo/documentation/*-FICTIF.md`.
 *
 * File format:
 * - front matter between two `---` lines: `title` (source cited), `product` (name of the device),
 *   `products` (catalogue codes, comma separated), `example` (sample question, optional);
 * - `[page N]` on its own line: the following text is on page N of the paper notice;
 * - `#` headings: section of the following paragraphs; `>` lines (warning) are not indexed;
 * - a passage = a paragraph (lines up to a blank line) or a list item (`- `).
 */
#[AsAlias(DocumentationCorpus::class)]
final class MarkdownDocumentationCorpus implements DocumentationCorpus
{
    public const DIRECTORY = 'data/yack/demo/documentation';
    public const PATTERN = '*-FICTIF.md';

    /** @var list<Notice>|null */
    private ?array $notices = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly string $directory = self::DIRECTORY,
    ) {
    }

    public function notices(): array
    {
        if (null !== $this->notices) {
            return $this->notices;
        }

        $files = glob(rtrim($this->projectDir, '/').'/'.trim($this->directory, '/').'/'.self::PATTERN) ?: [];
        sort($files);

        $notices = [];
        foreach ($files as $file) {
            $notices[] = self::parse((string) file_get_contents($file), basename($file));
        }

        return $this->notices = $notices;
    }

    /** @throws \InvalidArgumentException on a notice without front matter, title or page marker */
    public static function parse(string $markdown, string $file): Notice
    {
        $lines = preg_split('/\R/u', $markdown) ?: [];
        [$meta, $start] = self::frontMatter($lines, $file);

        $title = trim($meta['title'] ?? '');
        if ('' === $title) {
            throw new \InvalidArgumentException(sprintf('%s : « title » manquant dans l\'en-tête.', $file));
        }
        $codes = array_values(array_filter(array_map('trim', explode(',', $meta['products'] ?? '')), static fn (string $code): bool => '' !== $code));
        $example = trim($meta['example'] ?? '');

        $passages = [];
        $page = null;
        $section = null;
        $paragraph = [];
        $flush = static function () use (&$paragraph, &$passages, &$page, &$section, $title, $codes, $file): void {
            if ([] === $paragraph) {
                return;
            }
            if (null === $page) {
                throw new \InvalidArgumentException(sprintf('%s : texte avant le premier repère [page N].', $file));
            }
            $passages[] = new Passage($title, $page, implode(' ', $paragraph), $codes, $section);
            $paragraph = [];
        };

        foreach (\array_slice($lines, $start) as $line) {
            $line = trim($line);
            if (1 === preg_match('/^\[page\s+(\d+)\]$/i', $line, $match)) {
                $flush();
                $page = (int) $match[1];
            } elseif ('' === $line || str_starts_with($line, '>')) {
                $flush();
            } elseif (1 === preg_match('/^#+\s*(.+)$/u', $line, $match)) {
                $flush();
                $section = self::plain($match[1]);
            } elseif (1 === preg_match('/^[-*]\s+(.+)$/u', $line, $match)) {
                $flush();
                $paragraph[] = self::plain($match[1]);
                $flush();
            } else {
                $paragraph[] = self::plain($line);
            }
        }
        $flush();

        return new Notice($title, trim($meta['product'] ?? '') ?: $title, $codes, '' === $example ? null : $example, $passages);
    }

    /**
     * @param list<string> $lines
     *
     * @return array{0: array<string, string>, 1: int} values and index of the first body line
     */
    private static function frontMatter(array $lines, string $file): array
    {
        if ('---' !== trim($lines[0] ?? '')) {
            throw new \InvalidArgumentException(sprintf('%s : en-tête « --- » manquant.', $file));
        }

        $meta = [];
        foreach (\array_slice($lines, 1, null, true) as $index => $line) {
            if ('---' === trim($line)) {
                return [$meta, $index + 1];
            }
            if (1 === preg_match('/^([a-z_]+)\s*:\s*(.*)$/', trim($line), $match)) {
                $meta[$match[1]] = $match[2];
            }
        }

        throw new \InvalidArgumentException(sprintf('%s : en-tête non fermé.', $file));
    }

    /** Markdown emphasis and code marks removed: the passage is shown as plain text. */
    private static function plain(string $text): string
    {
        return trim(str_replace(['**', '__', '`'], '', $text));
    }
}
