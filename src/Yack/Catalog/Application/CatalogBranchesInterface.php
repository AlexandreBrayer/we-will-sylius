<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

/**
 * Public entry point of Catalog for the two branches of the shop menu (ADR 008): codes of the root taxon of the
 * finished products (Clik&Yack) and of the spare parts (Clik&Yack Parts). Read by Steering (CA by range, parts).
 */
interface CatalogBranchesInterface
{
    public function finishedProductsRootCode(): string;

    public function sparePartsRootCode(): string;
}
