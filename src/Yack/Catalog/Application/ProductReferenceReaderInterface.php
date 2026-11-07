<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductReference;

/**
 * Public entry point of Catalog for a product designated by its reference (ADR 008): AfterSales reads
 * through it the reference printed on a label, never through the Catalog Infrastructure.
 */
interface ProductReferenceReaderInterface
{
    /** Enabled product whose code is the reference as read (ProductReference::candidates()), null otherwise. */
    public function find(string $printedReference): ?ProductReference;
}
