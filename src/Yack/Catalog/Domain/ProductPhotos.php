<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Port: the product images of the shop catalog (Sylius adapter), for photos that arrive after the catalog
 * was loaded with generic visuals.
 */
interface ProductPhotos
{
    /**
     * Puts the photo in place of the generic visual the product was loaded with; nothing else of the product changes.
     *
     * @return bool false when there is nothing to replace: unknown product, or an image that is not the generic
     *              visual (the photo already, or an image chosen in the back-office)
     */
    public function replaceFallback(string $productCode, string $photoPath, string $fallbackPath): bool;
}
