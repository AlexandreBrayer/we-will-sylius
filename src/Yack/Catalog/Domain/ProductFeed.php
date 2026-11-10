<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Result of reading a batch from the Yack source: valid products plus the records the
 * adapter could not even map (malformed payloads).
 */
final readonly class ProductFeed
{
    /**
     * @param list<SourceProduct>                $products
     * @param list<RejectedRecord>               $rejected
     * @param array<string, array<string, mixed>> $payloads record of each product as received, by code (kept with a rejection)
     * @param array<string, int>                 $lines    line of each product in the file, by code (manual import)
     */
    public function __construct(
        public string $origin,
        public array $products,
        public array $rejected = [],
        public array $payloads = [],
        public array $lines = [],
    ) {
    }
}
