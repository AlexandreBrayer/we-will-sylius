<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Card\Simulated;

use App\Yack\Payment\Domain\Card\MaskedCard;

/**
 * What the simulated provider does with a test card, chosen by its last four digits (the
 * hosted page lists the full test numbers, docs/POC_YACK.md):
 *
 * | Test card           | Last digits | Outcome                             |
 * |---------------------|-------------|-------------------------------------|
 * | 4242 4242 4242 4242 | 4242        | accepted                            |
 * | 5555 5555 5555 4444 | 4444        | accepted                            |
 * | 4000 0000 0000 3220 | 3220        | 3-D Secure challenge (code 123456)  |
 * | 4000 0000 0000 9995 | 9995        | refused: insufficient funds         |
 * | 4000 0000 0000 0002 | 0002        | refused: declined by the bank       |
 *
 * Any other card is refused as unknown to the demonstration environment.
 */
enum SimulatedScenario
{
    case Accepted;
    case Challenge;
    case InsufficientFunds;
    case Declined;
    case Unknown;

    public static function forCard(MaskedCard $card): self
    {
        return match ($card->last4) {
            '4242', '4444' => self::Accepted,
            '3220' => self::Challenge,
            '9995' => self::InsufficientFunds,
            '0002' => self::Declined,
            default => self::Unknown,
        };
    }

    public function refusalReason(): ?string
    {
        return match ($this) {
            self::InsufficientFunds => 'fonds insuffisants',
            self::Declined => 'carte refusée par la banque',
            self::Unknown => 'carte inconnue de l\'environnement de démonstration',
            self::Accepted, self::Challenge => null,
        };
    }
}
