<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use App\Yack\Payment\Domain\Card\MaskedCard;

/**
 * Masked card as the shop API shows it.
 */
final class CardView
{
    /** @return ($card is null ? null : array{brand: string, brandLabel: string, last4: string, label: string, expiry: string}) */
    public static function of(?MaskedCard $card): ?array
    {
        return null === $card ? null : [
            'brand' => $card->brand->value,
            'brandLabel' => $card->brand->label(),
            'last4' => $card->last4,
            'label' => $card->label(),
            'expiry' => $card->expiry(),
        ];
    }
}
