<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * Gravité d'une alerte du pilotage, dans l'ordre d'affichage.
 */
enum AlertSeverity: string
{
    case Critical = 'critique';
    case Watch = 'a_surveiller';
    case Info = 'pour_info';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critique',
            self::Watch => 'À surveiller',
            self::Info => 'Pour info',
        };
    }

    /** Counted in the menu badges (critique and à surveiller). */
    public function needsAction(): bool
    {
        return self::Info !== $this;
    }
}
