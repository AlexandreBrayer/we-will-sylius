<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * Card network, as detected by the hosted payment page from the card number (only the brand
 * reaches Yack, never the number).
 */
enum CardBrand: string
{
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case Amex = 'amex';
    case Cb = 'cb';

    public function label(): string
    {
        return match ($this) {
            self::Visa => 'Visa',
            self::Mastercard => 'Mastercard',
            self::Amex => 'American Express',
            self::Cb => 'CB',
        };
    }
}
