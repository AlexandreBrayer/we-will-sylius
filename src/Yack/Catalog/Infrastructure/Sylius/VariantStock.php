<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Sylius;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;

/**
 * Stock of a sellable variant as shown to customers (catalogue, product page, SAV parts list).
 * Yack (ERP) is the master of the stock, synchronised into the Sylius "on hand" quantity.
 */
final readonly class VariantStock
{
    public function __construct(private AvailabilityCheckerInterface $availabilityChecker)
    {
    }

    /** @return array{tracked: bool, available: int|null, inStock: bool, label: string} */
    public function of(?ProductVariantInterface $variant): array
    {
        if (null === $variant) {
            return ['tracked' => false, 'available' => null, 'inStock' => false, 'label' => 'Indisponible'];
        }

        $available = $variant->isTracked() ? max(0, (int) $variant->getOnHand() - (int) $variant->getOnHold()) : null;
        $inStock = $this->availabilityChecker->isStockAvailable($variant);

        return [
            'tracked' => $variant->isTracked(),
            'available' => $available,
            'inStock' => $inStock,
            'label' => $inStock ? 'En stock' : 'Sur commande',
        ];
    }
}
