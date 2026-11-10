<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** What a synchronisation did (or, in a simulation, would do) with one record of a batch. */
enum SyncOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Disabled = 'disabled';
    case Unchanged = 'unchanged';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Créé',
            self::Updated => 'Mis à jour',
            self::Disabled => 'Désactivé',
            self::Unchanged => 'Inchangé',
            self::Failed => 'Rejeté',
        };
    }

    /** Plural label of the counters (« 2 mis à jour »). */
    public function plural(): string
    {
        return match ($this) {
            self::Created => 'Créés',
            self::Updated => 'Mis à jour',
            self::Disabled => 'Désactivés',
            self::Unchanged => 'Inchangés',
            self::Failed => 'Rejetés',
        };
    }

    /** True when the record changes the shop catalog. */
    public function writes(): bool
    {
        return in_array($this, [self::Created, self::Updated, self::Disabled], true);
    }
}
