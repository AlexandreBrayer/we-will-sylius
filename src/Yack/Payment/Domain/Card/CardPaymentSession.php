<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * Session opened at the payment service provider: the customer is sent to `redirectUrl`
 * (hosted payment page, or straight back to Yack when a saved card needs no authentication).
 */
final readonly class CardPaymentSession
{
    public function __construct(
        public string $id,
        public string $redirectUrl,
    ) {
    }
}
