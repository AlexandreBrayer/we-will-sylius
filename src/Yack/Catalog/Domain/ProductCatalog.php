<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Port: the e-shop catalog the Yack data is written into (Sylius adapter).
 */
interface ProductCatalog
{
    /**
     * Creates, updates or disables the product so that it mirrors the source exactly.
     *
     * @throws InvalidSourceProduct when the product references unknown data (taxon, attribute, image...)
     */
    public function apply(SourceProduct $product): SyncOutcome;

    /** Makes the applied changes durable. */
    public function commit(): void;
}
