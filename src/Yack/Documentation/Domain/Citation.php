<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/**
 * Where a sentence of an answer comes from: a notice and one of its pages. `looseKey()`: the same source written
 * otherwise by a language model (case, accents, punctuation and spaces ignored), same page.
 */
final readonly class Citation
{
    public function __construct(
        public string $source,
        public int $page,
    ) {
        if ('' === trim($source)) {
            throw new \InvalidArgumentException('Source de documentation vide.');
        }
        if ($page < 1) {
            throw new \InvalidArgumentException(sprintf('Page de documentation invalide : %d.', $page));
        }
    }

    public function key(): string
    {
        return $this->source."\u{0}".$this->page;
    }

    public function looseKey(): string
    {
        $source = (string) \Normalizer::normalize($this->source, \Normalizer::FORM_D);
        $source = (string) preg_replace('/\p{Mn}+/u', '', $source);
        $source = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($source)));

        return $source."\u{0}".$this->page;
    }

    public function equals(self $other): bool
    {
        return $this->key() === $other->key();
    }

    /** @return array{source: string, page: int} */
    public function toArray(): array
    {
        return ['source' => $this->source, 'page' => $this->page];
    }
}
