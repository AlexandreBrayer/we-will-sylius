<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Port: products and categories of the shop, as the search engine indexes them.
 */
interface SearchDocumentSource
{
    /**
     * Products of the shop channel, enabled or not (a disabled product is indexed as such and never found).
     *
     * @param list<string>|null $codes only these products; null: all of them
     *
     * @return list<SearchDocument>
     */
    public function products(?array $codes = null): array;

    /** @return list<CategoryDocument> */
    public function categories(): array;
}
