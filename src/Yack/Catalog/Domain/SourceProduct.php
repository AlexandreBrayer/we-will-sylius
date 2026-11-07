<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * A product as published by the Yack PIM/ERP (Yack is the master of this data).
 */
final readonly class SourceProduct
{
    /**
     * @param list<string>                         $taxonCodes additional taxons (brand, ...)
     * @param array<string, string|int|float|bool> $attributes attribute code => value
     */
    public function __construct(
        public string $code,
        public string $name,
        public bool $active,
        public ProductKind $kind,
        public string $mainTaxonCode,
        public int $priceInCents,
        public int $stock,
        public array $taxonCodes = [],
        public array $attributes = [],
        public ?string $shortDescription = null,
        public ?string $description = null,
        public ?string $imagePath = null,
    ) {
        if ('' === trim($code)) {
            throw new InvalidSourceProduct('Product code must not be empty.');
        }
        if ('' === trim($name)) {
            throw new InvalidSourceProduct(sprintf('Product "%s" has an empty name.', $code));
        }
        if ($priceInCents < 0) {
            throw new InvalidSourceProduct(sprintf('Product "%s" has a negative price (%d cents).', $code, $priceInCents));
        }
        if ($stock < 0) {
            throw new InvalidSourceProduct(sprintf('Product "%s" has a negative stock (%d).', $code, $stock));
        }
    }
}
