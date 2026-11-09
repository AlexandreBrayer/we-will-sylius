<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Port: full text search of the enabled products of the shop (Meilisearch and database adapters).
 */
interface ProductSearch
{
    /**
     * @throws SearchUnavailable when the engine cannot answer
     */
    public function search(SearchQuery $query): SearchResult;

    /**
     * Products and categories proposed for what the customer is typing.
     *
     * @throws SearchUnavailable when the engine cannot answer
     */
    public function suggest(string $text, int $productLimit, int $categoryLimit): Suggestions;
}
