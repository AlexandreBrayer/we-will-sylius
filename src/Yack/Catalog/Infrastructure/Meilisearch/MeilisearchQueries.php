<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\CategorySuggestion;
use App\Yack\Catalog\Domain\ProductSuggestion;
use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchQuery;
use App\Yack\Catalog\Domain\SearchResult;
use App\Yack\Catalog\Domain\SearchUnavailable;
use App\Yack\Catalog\Domain\Suggestions;

/**
 * Bodies of the Meilisearch search requests and reading of their answers. Only enabled products
 * are searched; an answer without the expected shape is a SearchUnavailable.
 */
final class MeilisearchQueries
{
    private const ENABLED = 'enabled = true';

    /** Every word of the query must be found: a full reference finds that product only. */
    public const ALL_WORDS = 'all';

    /** Words are dropped from the end of the query until products are found. */
    public const LAST_WORDS_OPTIONAL = 'last';

    /**
     * Body of POST /indexes/{products}/search: one page, exhaustive total (`totalHits`).
     *
     * @return array<string, mixed>
     */
    public static function search(SearchQuery $query, string $matchingStrategy = self::ALL_WORDS): array
    {
        $filter = [self::ENABLED];
        if (null !== $query->kind) {
            $filter[] = sprintf('kind = "%s"', $query->kind->value);
        }

        return [
            'q' => $query->text,
            'page' => $query->page,
            'hitsPerPage' => $query->perPage,
            'filter' => implode(' AND ', $filter),
            'matchingStrategy' => $matchingStrategy,
            'attributesToRetrieve' => ['code'],
        ];
    }

    /**
     * Body of POST /multi-search: products and categories in one round trip, the best ones first
     * even when the last word typed matches nothing yet.
     *
     * @return array{queries: list<array<string, mixed>>}
     */
    public static function suggest(MeilisearchIndexes $indexes, string $text, int $productLimit, int $categoryLimit): array
    {
        return ['queries' => [
            [
                'indexUid' => $indexes->products(),
                'q' => $text,
                'limit' => $productLimit,
                'filter' => self::ENABLED,
                'matchingStrategy' => self::LAST_WORDS_OPTIONAL,
                'attributesToRetrieve' => MeilisearchIndexes::PRODUCT_DISPLAYED,
            ],
            [
                'indexUid' => $indexes->categories(),
                'q' => $text,
                'limit' => $categoryLimit,
                'matchingStrategy' => self::LAST_WORDS_OPTIONAL,
                'attributesToRetrieve' => MeilisearchIndexes::CATEGORY_DISPLAYED,
            ],
        ]];
    }

    /**
     * @param array<mixed> $answer
     *
     * @throws SearchUnavailable
     */
    public static function searchResult(array $answer): SearchResult
    {
        $total = $answer['totalHits'] ?? $answer['estimatedTotalHits'] ?? null;
        if (!is_int($total)) {
            throw new SearchUnavailable('Meilisearch search answer without total.');
        }

        $codes = [];
        foreach (self::hits($answer) as $hit) {
            if (is_string($hit['code'] ?? null)) {
                $codes[] = $hit['code'];
            }
        }

        return new SearchResult($codes, $total, SearchEngine::Meilisearch);
    }

    /**
     * @param array<mixed> $answer answer of the multi-search built by suggest()
     *
     * @throws SearchUnavailable
     */
    public static function suggestions(array $answer): Suggestions
    {
        $results = $answer['results'] ?? null;
        if (!is_array($results) || !is_array($results[0] ?? null) || !is_array($results[1] ?? null)) {
            throw new SearchUnavailable('Meilisearch multi-search answer without its two results.');
        }

        $products = [];
        foreach (self::hits($results[0]) as $hit) {
            if (is_string($hit['code'] ?? null) && is_string($hit['name'] ?? null) && is_string($hit['slug'] ?? null)) {
                $products[] = new ProductSuggestion($hit['code'], $hit['name'], $hit['slug'], self::text($hit['brand'] ?? null), self::text($hit['image'] ?? null));
            }
        }

        $categories = [];
        foreach (self::hits($results[1]) as $hit) {
            if (is_string($hit['code'] ?? null) && is_string($hit['name'] ?? null) && is_string($hit['slug'] ?? null)) {
                $categories[] = new CategorySuggestion($hit['code'], $hit['name'], $hit['slug']);
            }
        }

        return new Suggestions($products, $categories, SearchEngine::Meilisearch);
    }

    /**
     * @param array<mixed> $answer
     *
     * @return list<array<mixed>>
     */
    private static function hits(array $answer): array
    {
        $hits = $answer['hits'] ?? null;
        if (!is_array($hits)) {
            throw new SearchUnavailable('Meilisearch answer without hits.');
        }

        return array_values(array_filter($hits, 'is_array'));
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && '' !== trim($value) ? $value : null;
    }
}
