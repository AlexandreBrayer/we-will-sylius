<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Port: the e-shop catalog the Yack data is written into (Sylius adapter).
 */
interface ProductCatalog
{
    /** The product of this code as the shop holds it now, null when it does not exist yet. */
    public function current(string $code): ?CatalogProduct;

    /**
     * Checks that the product can be applied (every category, brand, attribute and image it references
     * exists), without changing anything: what a simulation does instead of apply().
     *
     * @throws InvalidSourceProduct
     */
    public function check(SourceProduct $product): void;

    /**
     * Creates, updates or disables the product so that it mirrors the source exactly.
     *
     * @throws InvalidSourceProduct when the product references unknown data (taxon, attribute, image...)
     */
    public function apply(SourceProduct $product): void;

    /** Makes the applied changes durable. */
    public function commit(): void;
}
