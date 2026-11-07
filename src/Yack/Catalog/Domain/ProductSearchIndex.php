<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Port: the index of the search engine (Meilisearch adapter), fed from the shop catalogue.
 */
interface ProductSearchIndex
{
    /**
     * Rebuilds the whole index (settings included) from these documents.
     *
     * @param list<SearchDocument>   $products
     * @param list<CategoryDocument> $categories
     *
     * @throws SearchUnavailable
     */
    public function replaceAll(array $products, array $categories): void;

    /**
     * Adds or updates these products, the others stay as they are.
     *
     * @param list<SearchDocument> $products
     *
     * @throws SearchUnavailable
     */
    public function upsert(array $products): void;
}
