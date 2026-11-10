<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Values of a listed product that the catalogue can be filtered on (product attributes
 * "marque", "gamme", "puissance_kw" and "fluide_frigorigene", price), null when the product has none.
 * Price: the price the shop shows, in euros HT (lowest enabled variant of the channel).
 */
final readonly class ProductFacetValues
{
    public function __construct(
        public ?string $brand = null,
        public ?string $range = null,
        public ?float $powerKw = null,
        public ?string $refrigerant = null,
        public ?float $price = null,
    ) {
    }
}
