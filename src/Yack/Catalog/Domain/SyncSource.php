<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** Where the records of a synchronisation come from. */
enum SyncSource: string
{
    /** Batch of the Yack PIM/ERP API (delta feed). */
    case Api = 'api';
    /** Manual import of a CSV file from the back-office (degraded mode). */
    case Csv = 'csv';
    /** Manual import of an XLSX file from the back-office (degraded mode). */
    case Xlsx = 'xlsx';
    /** Rejected records of an earlier run, applied again after correction. */
    case Replay = 'replay';

    public function label(): string
    {
        return match ($this) {
            self::Api => 'API PIM/ERP',
            self::Csv => 'Import CSV',
            self::Xlsx => 'Import XLSX',
            self::Replay => 'Rejeu des rejets',
        };
    }

    public function isManualImport(): bool
    {
        return self::Csv === $this || self::Xlsx === $this;
    }
}
