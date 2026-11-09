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
     * Products and categories proposed for what the customer is typing. With a category code
     * ("Rechercher dans la liste" of a category page): the products whose main category is this one
     * or one of its subcategories, and its subcategories only; an unknown code proposes nothing.
     *
     * @throws SearchUnavailable when the engine cannot answer
     */
    public function suggest(string $text, int $productLimit, int $categoryLimit, ?string $category = null): Suggestions;
}
