<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * Outcome of a card payment session, as read from the payment service provider.
 */
enum CardPaymentStatus: string
{
    /** The customer is still on the hosted payment page (card entry, 3-D Secure). */
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Paiement en cours',
            self::Accepted => 'Paiement accepté',
            self::Refused => 'Paiement refusé',
            self::Cancelled => 'Paiement annulé',
        };
    }

    public function isConcluded(): bool
    {
        return self::Pending !== $this;
    }
}
