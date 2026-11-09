<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\StockLevel;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * Public entry point of Catalog for the stock of a variant (ADR 008): other contexts
 * (AfterSales) read the stock through this interface, never through the Catalog Infrastructure.
 */
interface VariantStockReaderInterface
{
    public function of(?ProductVariantInterface $variant): StockLevel;
}
