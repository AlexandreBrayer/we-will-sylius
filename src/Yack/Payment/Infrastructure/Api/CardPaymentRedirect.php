<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use App\Yack\Payment\Domain\Card\CardPaymentSession;

/**
 * Answer of POST /shop/yack/orders/{tokenValue}/card-payment: where to send the customer.
 */
final readonly class CardPaymentRedirect
{
    public function __construct(
        public string $sessionId,
        /** Hosted payment page (a front path for the simulator), or the Yack return page. */
        public string $redirectUrl,
    ) {
    }

    public static function of(CardPaymentSession $session): self
    {
        return new self($session->id, $session->redirectUrl);
    }
}
