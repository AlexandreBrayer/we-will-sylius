<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\SyncSource;

/** Formats of the manual import, recognised by the extension and checked on the content. */
enum ImportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    /** Types read on the content (fileinfo) accepted for each format. */
    private const CONTENT_TYPES = [
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'text/x-csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    ];

    /**
     * @param string $contentType type read on the content of the file, never the one the browser sent
     *
     * @throws ImportFileRefused
     */
    public static function of(string $fileName, string $contentType): self
    {
        $format = self::tryFrom(strtolower(pathinfo($fileName, \PATHINFO_EXTENSION)))
            ?? throw new ImportFileRefused('Format non accepté : fichier CSV ou XLSX attendu.');
        if (!in_array($contentType, self::CONTENT_TYPES[$format->value], true)) {
            throw new ImportFileRefused(sprintf('Le contenu du fichier ne correspond pas à un fichier %s.', $format->label()));
        }

        return $format;
    }

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function source(): SyncSource
    {
        return match ($this) {
            self::Csv => SyncSource::Csv,
            self::Xlsx => SyncSource::Xlsx,
        };
    }

    /** Type of the file sent to the browser (templates). */
    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
