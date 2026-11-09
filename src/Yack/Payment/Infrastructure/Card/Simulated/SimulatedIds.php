<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Card\Simulated;

/**
 * Identifiers issued by the simulated provider: session, transaction reference, card token.
 */
final class SimulatedIds
{
    public function session(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** "SIM-7F3A9C21E4": shown in the back-office like a Monext transaction id. */
    public function transactionReference(): string
    {
        return 'SIM-' . strtoupper(bin2hex(random_bytes(5)));
    }

    public function walletToken(): string
    {
        return 'simtok_' . bin2hex(random_bytes(12));
    }
}
