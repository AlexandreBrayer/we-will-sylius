<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * Result of a session as read from the payment service provider.
 */
final readonly class CardPaymentResult
{
    public function __construct(
        public CardPaymentStatus $status,
        public ?string $transactionReference = null,
        public ?MaskedCard $card = null,
        public bool $threeDSecure = false,
        /** Why the payment was refused, in French ("Fonds insuffisants"). */
        public ?string $refusalReason = null,
        /** Token of the card saved at the provider, when the customer asked for it. */
        public ?string $walletToken = null,
    ) {
    }

    public static function pending(): self
    {
        return new self(CardPaymentStatus::Pending);
    }

    public function walletCard(): ?WalletCard
    {
        return null === $this->walletToken || null === $this->card ? null : new WalletCard($this->walletToken, $this->card);
    }
}
