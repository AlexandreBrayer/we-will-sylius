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
     * @param list<SourceProduct>  $products
     * @param list<RejectedRecord> $rejected
     */
    public function __construct(
        public string $origin,
        public array $products,
        public array $rejected = [],
    ) {
    }
}
