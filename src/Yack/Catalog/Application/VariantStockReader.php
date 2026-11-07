<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\StockLevel;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;

/**
 * Facade (ADR 008): reads the Sylius stock of the variant, the level rule stays in the Domain.
 */
final readonly class VariantStockReader implements VariantStockReaderInterface
{
    public function __construct(private AvailabilityCheckerInterface $availabilityChecker)
    {
    }

    public function of(?ProductVariantInterface $variant): StockLevel
    {
        if (null === $variant) {
            return StockLevel::noVariant();
        }

        $tracked = $variant->isTracked();

        return StockLevel::of(
            $tracked,
            $tracked ? (int) $variant->getOnHand() - (int) $variant->getOnHold() : null,
            $this->availabilityChecker->isStockAvailable($variant),
        );
    }
}
