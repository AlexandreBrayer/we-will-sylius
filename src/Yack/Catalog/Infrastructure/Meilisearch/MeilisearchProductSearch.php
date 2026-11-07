<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\ProductSearch;
use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchQuery;
use App\Yack\Catalog\Domain\SearchResult;
use App\Yack\Catalog\Domain\Suggestions;

/**
 * Adapter: searches the Meilisearch indexes fed by MeilisearchProductIndex (relevance, typos,
 * trade synonyms, reference searched by its beginning). A query typed as a reference is first
 * searched in the codes and manufacturer references only: names carry references too, and with
 * one typo "SRK25" would also find every SRK35 and SRK20. Otherwise, or when no reference matches,
 * a search needs every word of the query; when no product has them all, the last words become
 * optional (one more request). Suggestions take one request.
 */
final readonly class MeilisearchProductSearch implements ProductSearch
{
    public function __construct(
        private MeilisearchClient $client,
        private MeilisearchIndexes $indexes,
    ) {
    }

    public function search(SearchQuery $query): SearchResult
    {
        if ($query->isBlank()) {
            return SearchResult::none(SearchEngine::Meilisearch);
        }

        if ($query->looksLikeReference()) {
            $byReference = $this->ask($query, MeilisearchQueries::ALL_WORDS, MeilisearchIndexes::REFERENCE_SEARCHABLE);
            if ($byReference->total > 0) {
                return $byReference;
            }
        }

        $result = $this->ask($query, MeilisearchQueries::ALL_WORDS);
        if (0 === $result->total && count($query->terms()) > 1) {
            $result = $this->ask($query, MeilisearchQueries::LAST_WORDS_OPTIONAL);
        }

        return $result;
    }

    public function suggest(string $text, int $productLimit, int $categoryLimit): Suggestions
    {
        if ('' === trim($text)) {
            return Suggestions::none(SearchEngine::Meilisearch);
        }

        return MeilisearchQueries::suggestions(
            $this->client->request('POST', '/multi-search', MeilisearchQueries::suggest($this->indexes, $text, $productLimit, $categoryLimit)),
        );
    }

    /** @param list<string>|null $attributes */
    private function ask(SearchQuery $query, string $matchingStrategy, ?array $attributes = null): SearchResult
    {
        return MeilisearchQueries::searchResult(
            $this->client->request('POST', sprintf('/indexes/%s/search', $this->indexes->products()), MeilisearchQueries::search($query, $matchingStrategy, $attributes)),
        );
    }
}
