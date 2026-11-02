<?php

declare(strict_types=1);

namespace App\Yack\Account\Domain;

/**
 * Security attributes exposed to the application (voter, Twig hooks, listeners).
 */
enum CommercePermission: string
{
    case SeePrices = 'YACK_SEE_PRICES';
    case PlaceOrder = 'YACK_PLACE_ORDER';

    public function isGrantedTo(CustomerProfile $profile): bool
    {
        return match ($this) {
            self::SeePrices => $profile->canSeePrices(),
            self::PlaceOrder => $profile->canPlaceOrder(),
        };
    }
}
