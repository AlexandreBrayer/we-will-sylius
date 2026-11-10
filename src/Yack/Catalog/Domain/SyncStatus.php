<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Result of a whole run: every record applied (success), some rejected (partial), or nothing read
 * because the source did not answer (failure).
 */
enum SyncStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failure = 'failure';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Succès',
            self::Partial => 'Partiel',
            self::Failure => 'Échec',
        };
    }
}
