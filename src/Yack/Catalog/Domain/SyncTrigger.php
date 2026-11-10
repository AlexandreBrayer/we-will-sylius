<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** Who started a synchronisation: the command line (a person or the scheduled task), or an administrator. */
enum SyncTrigger: string
{
    case Console = 'console';
    case BackOffice = 'back_office';

    public function label(): string
    {
        return match ($this) {
            self::Console => 'Ligne de commande',
            self::BackOffice => 'Back-office',
        };
    }
}
