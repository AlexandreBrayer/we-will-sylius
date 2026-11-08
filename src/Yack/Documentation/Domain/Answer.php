<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/**
 * Answer of the documentation assistant. Business rule: an answer always names its sources
 * (notice and page); the only answer without a source is "Je ne trouve pas la réponse dans la
 * documentation". `simulated`: produced by the demonstration adapter (YACK_AI_ADAPTER=fake).
 */
final readonly class Answer
{
    public const NOT_FOUND = 'Je ne trouve pas la réponse dans la documentation.';
    public const MAX_LENGTH = 2000;

    /** @param list<Citation> $sources */
    private function __construct(
        public string $text,
        public array $sources,
        public bool $simulated,
    ) {
    }

    /**
     * @param list<Citation> $sources
     *
     * @throws AnswerWithoutSource when no source is given
     */
    public static function cited(string $text, array $sources, bool $simulated = false): self
    {
        $text = trim($text);
        if ('' === $text) {
            throw new \InvalidArgumentException('Réponse vide.');
        }

        $unique = [];
        foreach ($sources as $source) {
            $unique[$source->key()] ??= $source;
        }
        if ([] === $unique) {
            throw AnswerWithoutSource::forText($text);
        }

        return new self(mb_substr($text, 0, self::MAX_LENGTH), array_values($unique), $simulated);
    }

    public static function notFound(bool $simulated = false): self
    {
        return new self(self::NOT_FOUND, [], $simulated);
    }

    public function isFound(): bool
    {
        return [] !== $this->sources;
    }

    /**
     * Keeps only the sources among `$passages` (what was really read); with no source left the
     * answer becomes "je ne trouve pas": a model citing a page it was not given is not trusted.
     * A source written otherwise than its passage (case, accents, punctuation, spaces: `Citation::looseKey`)
     * is kept as the passage writes it, when it designates a single passage source on that page.
     *
     * @param list<Passage> $passages
     */
    public function restrictedTo(array $passages): self
    {
        if (!$this->isFound()) {
            return $this;
        }

        /** @var array<string, Citation> $exact */
        $exact = [];
        /** @var array<string, array<string, Citation>> $loose */
        $loose = [];
        foreach ($passages as $passage) {
            $citation = $passage->citation();
            $exact[$citation->key()] = $citation;
            $loose[$citation->looseKey()][$citation->key()] = $citation;
        }

        $kept = [];
        foreach ($this->sources as $source) {
            $candidates = $loose[$source->looseKey()] ?? [];
            $match = $exact[$source->key()] ?? (1 === \count($candidates) ? reset($candidates) : null);
            if (null !== $match) {
                $kept[$match->key()] ??= $match;
            }
        }

        return [] === $kept ? self::notFound($this->simulated) : new self($this->text, array_values($kept), $this->simulated);
    }

    /** @return array{answer: string, sources: list<array{source: string, page: int}>, simulated: bool} */
    public function toArray(): array
    {
        return [
            'answer' => $this->text,
            'sources' => array_map(static fn (Citation $source): array => $source->toArray(), $this->sources),
            'simulated' => $this->simulated,
        ];
    }
}
