<?php

declare(strict_types=1);

namespace App\Yack\Account\Domain;

enum OrderAccessRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Refused => 'Refusée',
        };
    }
}
