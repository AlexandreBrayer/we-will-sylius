<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Application;

use App\Yack\Documentation\Domain\DocumentationCorpus;
use App\Yack\Documentation\Domain\Passage;
use App\Yack\Documentation\Domain\PassageRetriever;
use App\Yack\Documentation\Domain\Question;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Lexical retrieval in plain PHP (no engine, no model): BM25 score of each passage on the product
 * name of its notice, its section and its paragraph (LexicalAnalyzer terms), limited to the notices
 * of the product when the question comes from a product page.
 *
 * Relevance: the words of the question that are not in the product name of the notice ("PAC",
 * "Evolys", "8", "kW"…) are the content words; a passage is kept when it contains at least half of
 * them in its own section or paragraph, one of them at least being a word (a number or a unit alone,
 * "8", "kW", is not enough). "Evolys 8 kW" alone, or a question on something the notices do not talk about,
 * finds nothing.
 */
#[AsAlias(PassageRetriever::class)]
final class RetrievePassages implements PassageRetriever
{
    public const DEFAULT_LIMIT = 3;
    public const MIN_COVERAGE = 0.5;
    private const K1 = 1.2;
    private const B = 0.75;

    /** @var list<array{passage: Passage, tf: array<string, int>, length: int, own: array<string, true>, product: array<string, true>}>|null */
    private ?array $index = null;

    /** @var array<string, int> number of passages containing each term */
    private array $documentFrequency = [];

    private float $averageLength = 1.0;

    public function __construct(private readonly DocumentationCorpus $corpus)
    {
    }

    public function retrieve(Question $question, int $limit = self::DEFAULT_LIMIT): array
    {
        $queryTerms = LexicalAnalyzer::distinctTerms($question->text);
        if ($limit < 1 || [] === $queryTerms) {
            return [];
        }

        $index = $this->index();
        $count = \count($index);
        $scored = [];
        foreach ($index as $position => $entry) {
            if (null !== $question->productCode && !$entry['passage']->covers($question->productCode)) {
                continue;
            }

            $content = array_values(array_filter($queryTerms, static fn (string $term): bool => !isset($entry['product'][$term])));
            if ([] === $content) {
                continue;
            }
            $matched = array_filter($content, static fn (string $term): bool => isset($entry['own'][$term]));
            $words = array_filter($matched, static fn (string $term): bool => !LexicalAnalyzer::isQuantity($term));
            if ([] === $words || \count($matched) / \count($content) < self::MIN_COVERAGE) {
                continue;
            }

            $score = 0.0;
            foreach ($queryTerms as $term) {
                $frequency = $entry['tf'][$term] ?? 0;
                if (0 === $frequency) {
                    continue;
                }
                $documents = $this->documentFrequency[$term];
                $idf = log(1 + ($count - $documents + 0.5) / ($documents + 0.5));
                $norm = 1 - self::B + self::B * $entry['length'] / $this->averageLength;
                $score += $idf * ($frequency * (self::K1 + 1)) / ($frequency + self::K1 * $norm);
            }
            $scored[] = ['score' => $score, 'position' => $position, 'passage' => $entry['passage']];
        }

        usort($scored, static fn (array $a, array $b): int => [$b['score'], $a['position']] <=> [$a['score'], $b['position']]);

        return array_map(static fn (array $hit): Passage => $hit['passage'], \array_slice($scored, 0, $limit));
    }

    /** @return list<array{passage: Passage, tf: array<string, int>, length: int, own: array<string, true>, product: array<string, true>}> */
    private function index(): array
    {
        if (null !== $this->index) {
            return $this->index;
        }

        $index = [];
        $total = 0;
        foreach ($this->corpus->notices() as $notice) {
            foreach ($notice->passages as $passage) {
                $productTerms = LexicalAnalyzer::terms($notice->productName);
                $ownTerms = LexicalAnalyzer::terms($passage->indexedText());
                $all = [...$productTerms, ...$ownTerms];
                $tf = array_count_values($all);
                foreach (array_keys($tf) as $term) {
                    $this->documentFrequency[$term] = ($this->documentFrequency[$term] ?? 0) + 1;
                }
                $total += \count($all);
                $index[] = [
                    'passage' => $passage,
                    'tf' => $tf,
                    'length' => \count($all),
                    'own' => array_fill_keys($ownTerms, true),
                    'product' => array_fill_keys($productTerms, true),
                ];
            }
        }
        $this->averageLength = [] === $index ? 1.0 : max(1.0, $total / \count($index));

        return $this->index = $index;
    }
}
