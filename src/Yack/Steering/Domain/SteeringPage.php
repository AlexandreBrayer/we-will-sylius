<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * The three pages of the « Direction » group of the back-office menu.
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
