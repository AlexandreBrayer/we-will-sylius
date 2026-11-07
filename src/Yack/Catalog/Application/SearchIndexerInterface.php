<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\SearchUnavailable;

/**
 * Keeps the search engine index in line with the shop catalogue.
 */
interface SearchIndexerInterface
{
    /**
     * Rebuilds the whole index (products and categories, settings included).
     *
     * @return array{products: int, categories: int} number of indexed documents
     *
     * @throws SearchUnavailable
     */
    public function reindexAll(): array;

    /**
     * Updates these products in the index after a catalogue change. Never fails: an engine that
     * cannot be reached is logged, the next full reindex catches up.
     *
     * @param list<string> $codes
     */
    public function refresh(array $codes): void;
}
