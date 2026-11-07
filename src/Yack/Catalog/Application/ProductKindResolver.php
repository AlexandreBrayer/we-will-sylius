<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductKind;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Facade (ADR 008): reads the main category branch of the Sylius product, the rule stays in
 * the Domain (ProductKind::fromTaxonCodes()).
 */
final readonly class ProductKindResolver implements ProductKindResolverInterface
{
    public function kindOf(ProductInterface $product): ?ProductKind
    {
        $codes = [];
        $taxon = $product->getMainTaxon();
        while (null !== $taxon) {
            $code = $taxon->getCode();
            if (null !== $code) {
                $codes[] = $code;
            }
            $taxon = $taxon->getParent();
        }

        return ProductKind::fromTaxonCodes($codes);
    }
}
