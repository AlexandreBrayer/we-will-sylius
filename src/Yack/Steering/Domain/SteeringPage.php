<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * The three tabs of the back-office dashboard: Pilotage (the dashboard itself), SAV et pièces, Clients et encours.
 */
enum SteeringPage: string
{
    case Pilotage = 'pilotage';
    case AfterSales = 'sav-et-pieces';
    case Customers = 'clients-et-encours';

    public function label(): string
    {
        return match ($this) {
            self::Pilotage => 'Pilotage',
            self::AfterSales => 'SAV et pièces',
            self::Customers => 'Clients et encours',
        };
    }
}
