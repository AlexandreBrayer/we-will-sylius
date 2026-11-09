<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * Card saved at the payment service provider: Yack keeps the provider's token and the masked
 * card, never the card number.
 */
final readonly class WalletCard
{
    public function __construct(
        public string $token,
        public MaskedCard $card,
    ) {
        if ('' === $token) {
            throw new \InvalidArgumentException('A saved card needs the token of the payment service provider.');
        }
    }
}
