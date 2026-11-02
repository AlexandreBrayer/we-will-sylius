<?php

declare(strict_types=1);

namespace App\Yack\Installer\Domain;

enum Activity: string
{
    case Installation = 'installation';
    case Maintenance = 'maintenance';
    case Repair = 'depannage';

    public function label(): string
    {
        return match ($this) {
            self::Installation => 'Installation',
            self::Maintenance => 'Entretien',
            self::Repair => 'Dépannage',
        };
    }
}
