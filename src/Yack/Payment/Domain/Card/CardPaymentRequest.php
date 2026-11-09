<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * What Yack asks the payment service provider: amount of an order, where to send the customer
 * back, and the saved card to use for a one-click payment.
 */
final readonly class CardPaymentRequest
{
    public function __construct(
        public string $orderNumber,
        /** Merchant reference of this attempt (one per Sylius payment). */
        public string $reference,
        /** In cents. */
        public int $amount,
        public string $currencyCode,
        public string $customerEmail,
        /** Yack page the provider sends the customer back to, whatever the outcome. */
        public string $returnUrl,
        public ?WalletCard $walletCard = null,
    ) {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('A card payment needs a positive amount.');
        }
    }
}
