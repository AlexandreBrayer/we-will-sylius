<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * A product as the shop holds it now, read before a record of Yack is applied: the base of the
 * field-level differences of a synchronisation (ProductChange).
 */
final readonly class CatalogProduct
{
    /**
     * @param array<string, string|int|float|bool> $attributes attribute code => stored value
     */
    public function __construct(
        public string $code,
        public string $name,
        public bool $active,
        public ?int $priceInCents,
        public ?int $stock,
        public ?string $mainTaxonCode,
        public array $attributes = [],
        public ?string $shortDescription = null,
        public ?string $description = null,
    ) {
    }
}
