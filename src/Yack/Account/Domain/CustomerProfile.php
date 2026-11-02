<?php

declare(strict_types=1);

namespace App\Yack\Account\Domain;

/**
 * Commercial profile of the person browsing the shop.
 *
 * Business rule (CDC Yack): only ECOM customers see prices and can order online.
 * PRO customers (installers) get their negotiated conditions through their agency / ERP,
 * visitors must log in.
 */
enum CustomerProfile: string
{
    case Visitor = 'visitor';
    case Pro = 'pro';
    case Ecom = 'ecom';

    public const PRO_GROUP_CODE = 'pro';

    public const ECOM_GROUP_CODE = 'ecom';

    /**
     * Self-registered customers (no group) are e-commerce customers: PRO accounts are
     * always created and grouped by Yack. Any other group is treated as PRO (no public prices).
     */
    public static function fromCustomerGroupCode(?string $groupCode): self
    {
        return match ($groupCode) {
            null, self::ECOM_GROUP_CODE => self::Ecom,
            default => self::Pro,
        };
    }

    public function canSeePrices(): bool
    {
        return $this === self::Ecom;
    }

    public function canPlaceOrder(): bool
    {
        return $this === self::Ecom;
    }

    public function label(): string
    {
        return match ($this) {
            self::Visitor => 'Visiteur',
            self::Pro => 'Compte PRO',
            self::Ecom => 'Compte ECOM',
        };
    }
}
