<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductKind;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Public entry point of Catalog for the kind of a product (ADR 008): other contexts (Payment)
 * read it through this interface, never through the Catalog Infrastructure.
 */
interface ProductKindResolverInterface
{
    /** Finished product or spare part, null when the main category is outside both menu branches. */
    public function kindOf(ProductInterface $product): ?ProductKind;
}
