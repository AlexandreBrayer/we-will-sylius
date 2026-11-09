<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Values of a listed product that the catalogue can be filtered on (product attributes
 * "marque", "gamme", "puissance_kw" and "fluide_frigorigene"), null when the product has none.
 */
final readonly class ProductFacetValues
{
    public function __construct(
        public ?string $brand = null,
        public ?string $range = null,
        public ?float $powerKw = null,
        public ?string $refrigerant = null,
    ) {
    }
}
