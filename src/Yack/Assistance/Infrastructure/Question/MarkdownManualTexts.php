<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Question;

use App\Yack\Assistance\Domain\Manual;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Texte indexé d'un manuel : fichier Markdown du dossier data/yack/assistance désigné par `Manual::$text`
 * (`textes/<id>.md`), produit hors du dépôt par le générateur de la base à partir des pages extraites du PDF.
 *
 * Format :
 * - en-tête entre deux lignes `---` (`document`, `title`, `langue`, `pages_indexees`), ignoré ici : la base fait foi ;
 * - `[page N]` seul sur sa ligne : le texte qui suit est à la page N du PDF ;
 * - `#` titres : section des paragraphes qui suivent (remise à zéro à chaque page) ;
 * - un passage = un paragraphe (lignes jusqu'à une ligne vide) ou un élément de liste (`- `).
 *
 * Mise en page (`layout`), mesurée sur le texte extrait des PDF :
 * - `tableau` : passage d'au moins 3 séparateurs de cellule ` | ` : lignes d'un tableau aplaties par l'extraction,
 *   cellules de plusieurs lignes mélangées mais encore côte à côte ;
 * - `grille` : tout passage d'une page où au moins 5 titres différents se répètent (« Clignote-2 fois »…) : un
 *   tableau éclaté en titres et en fragments, le lien entre une cellule et sa ligne est perdu ;
 * - `texte` : le reste.
 */
final class MarkdownManualTexts
{
    public const TEXT_PATTERN = '#^textes/[a-z0-9][a-z0-9-]*\.md$#';
    public const TABLE_SEPARATORS = 3;
    public const GRID_REPEATED_HEADINGS = 5;

    public const LAYOUT_TEXT = 'texte';
    public const LAYOUT_TABLE = 'tableau';
    public const LAYOUT_GRID = 'grille';

    /** @var array<string, list<array{page: int, section: ?string, text: string, layout: string}>> */
    private array $read = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/data/yack/assistance')]
        private readonly string $directory,
    ) {
    }

    /**
     * Passages du texte indexé ; vide si le manuel n'a pas de texte, si le chemin sort du dossier des textes ou si
     * le fichier est absent.
     *
     * @return list<array{page: int, section: ?string, text: string, layout: string}>
     */
    public function passages(Manual $manual): array
    {
        $path = $manual->text;
        if (null === $path || 1 !== preg_match(self::TEXT_PATTERN, $path)) {
            return [];
        }
        if (!\array_key_exists($path, $this->read)) {
            $file = rtrim($this->directory, '/') . '/' . $path;
            $this->read[$path] = is_file($file) ? self::parse((string) file_get_contents($file), $path) : [];
        }

        return $this->read[$path];
    }

    /**
     * @return list<array{page: int, section: ?string, text: string, layout: string}>
     *
     * @throws \InvalidArgumentException texte avant le premier repère `[page N]`
     */
    public static function parse(string $markdown, string $file): array
    {
        $lines = preg_split('/\R/u', $markdown) ?: [];
        $start = 0;
        if ('---' === trim($lines[0] ?? '')) {
            foreach (\array_slice($lines, 1, null, true) as $index => $line) {
                if ('---' === trim($line)) {
                    $start = $index + 1;
                    break;
                }
            }
        }

        $passages = [];
        // État partagé avec $flush, qui le lit par référence : page et titre en cours, lignes du paragraphe
        /** @var int|null $page */
        $page = null;
        /** @var string|null $section */
        $section = null;
        /** @var list<string> $paragraph */
        $paragraph = [];
        /** @var array<int, array<string, int>> $headings titres de chaque page et leur nombre */
        $headings = [];
        $flush = static function () use (&$paragraph, &$passages, &$page, &$section, $file): void {
            if ([] === $paragraph) {
                return;
            }
            if (null === $page) {
                throw new \InvalidArgumentException(sprintf('%s : texte avant le premier repère [page N].', $file));
            }
            $text = implode(' ', $paragraph);
            $passages[] = ['page' => $page, 'section' => $section, 'text' => $text, 'layout' => substr_count($text, ' | ') >= self::TABLE_SEPARATORS ? self::LAYOUT_TABLE : self::LAYOUT_TEXT];
            $paragraph = [];
        };

        foreach (\array_slice($lines, $start) as $line) {
            $line = trim($line);
            if (1 === preg_match('/^\[page\s+(\d+)\]$/i', $line, $match)) {
                $flush();
                $page = (int) $match[1];
                $section = null;
            } elseif ('' === $line) {
                $flush();
            } elseif (1 === preg_match('/^#+\s*(.+)$/u', $line, $match)) {
                $flush();
                $section = trim($match[1]);
                if (null !== $page) {
                    $headings[$page][$section] = ($headings[$page][$section] ?? 0) + 1;
                }
            } elseif (1 === preg_match('/^[-*]\s+(.+)$/u', $line, $match)) {
                $flush();
                $paragraph[] = trim($match[1]);
                $flush();
            } else {
                $paragraph[] = $line;
            }
        }
        $flush();

        $grids = [];
        foreach ($headings as $number => $counts) {
            if (\count(array_filter($counts, static fn (int $count): bool => $count > 1)) >= self::GRID_REPEATED_HEADINGS) {
                $grids[$number] = true;
            }
        }

        return array_map(
            static fn (array $passage): array => isset($grids[$passage['page']]) ? array_replace($passage, ['layout' => self::LAYOUT_GRID]) : $passage,
            $passages,
        );
    }

    /**
     * Pages d'une liste « 45, 82-83, 85-86 » (champ `indexedPages` de la base), dans l'ordre, sans doublon.
     *
     * @return list<int>
     */
    public static function pages(?string $indexedPages): array
    {
        $pages = [];
        foreach (preg_split('/\s*,\s*/', trim((string) $indexedPages), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $range) {
            if (1 === preg_match('/^(\d+)(?:\s*-\s*(\d+))?$/', $range, $match)) {
                $last = isset($match[2]) ? (int) $match[2] : (int) $match[1];
                for ($page = (int) $match[1]; $page <= $last; ++$page) {
                    $pages[$page] = $page;
                }
            }
        }

        return array_values($pages);
    }
}
