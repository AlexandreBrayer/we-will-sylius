<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Card\Simulated;

/**
 * Where a session of the simulated provider stands.
 */
enum SimulatedStep: string
{
    /** Hosted page: card entry. */
    case Card = 'card';
    /** Bank page: 3-D Secure code sent by SMS. */
    case Challenge = 'challenge';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return self::Card === $this || self::Challenge === $this;
    }
}
