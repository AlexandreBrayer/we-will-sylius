<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application\Import;

use App\Yack\Catalog\Application\ApplyProductFeed;
use App\Yack\Catalog\Domain\Import\ColumnMapping;
use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\ImportFiles;
use App\Yack\Catalog\Domain\Import\SpreadsheetReader;
use App\Yack\Catalog\Domain\Import\SpreadsheetRows;
use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductRecordMapper;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncTrigger;

/**
 * Manual import of the catalog from the back-office, when the Yack API does not answer (degraded mode):
 * 1. the file is stored outside the public directory; 2. the preview reads it (columns recognised,
 * lines rejected with their reason, changes against the shop) without writing; 3. the confirmation
 * applies it through the same use case as the API feed (ApplyProductFeed), recorded as a run, and the
 * file is deleted. Each line becomes a record of the API feed and goes through the same mapping.
 */
final readonly class ImportCatalogFile
{
    public function __construct(
        private ImportFiles $files,
        private SpreadsheetReader $reader,
        private ProductRecordMapper $mapper,
        private ApplyProductFeed $apply,
    ) {
    }

    /**
     * @throws ImportFileRefused
     */
    public function store(string $uploadedPath, string $originalName): ImportFile
    {
        return $this->files->store($uploadedPath, $originalName);
    }

    /** The stored file of this token, null for an unknown or malformed token. */
    public function file(string $token): ?ImportFile
    {
        return ImportFile::isToken($token) ? $this->files->find($token) : null;
    }

    /**
     * @throws ImportFileRefused
     */
    public function preview(ImportFile $file): ImportPreview
    {
        $rows = $this->reader->read($file);
        $mapping = ColumnMapping::detect($rows->headers);

        return new ImportPreview($file, $rows, $mapping, $this->apply->preview($this->feed($file, $rows, $mapping)));
    }

    /**
     * Applies the file, then deletes it.
     *
     * @throws ImportFileRefused when a required column is missing
     */
    public function confirm(ImportFile $file, ?string $operator): SyncReport
    {
        $rows = $this->reader->read($file);
        $mapping = ColumnMapping::detect($rows->headers);
        if ([] !== ($missing = $mapping->missing())) {
            throw new ImportFileRefused(self::missingMessage($missing));
        }

        $report = ($this->apply)(
            $this->feed($file, $rows, $mapping),
            new SyncContext($file->format->source(), SyncTrigger::BackOffice, $operator),
        );
        $this->files->delete($file);

        return $report;
    }

    public function cancel(ImportFile $file): void
    {
        $this->files->delete($file);
    }

    /** @param list<ImportColumn> $missing */
    public static function missingMessage(array $missing): string
    {
        return sprintf(
            'Colonnes obligatoires absentes : %s. Téléchargez le modèle pour voir les en-têtes attendus.',
            implode(', ', array_map(static fn (ImportColumn $column): string => $column->header(), $missing)),
        );
    }

    private function feed(ImportFile $file, SpreadsheetRows $rows, ColumnMapping $mapping): ProductFeed
    {
        if ([] !== $mapping->missing()) {
            return new ProductFeed($file->originalName, []);
        }

        $products = [];
        $rejected = [];
        $payloads = [];
        $lines = [];
        $seen = [];
        foreach ($rows->rows as $number => $cells) {
            $line = $mapping->line($cells);
            $code = $line->code() ?? sprintf('Ligne %d', $number);
            if (isset($seen[$code])) {
                $rejected[] = new RejectedRecord($code, sprintf('code déjà présent ligne %d : seule la première ligne est importée.', $seen[$code]), $line->record, $number);

                continue;
            }
            $seen[$code] = $number;
            if (!$line->isValid()) {
                $rejected[] = new RejectedRecord($code, $line->reason(), $line->record, $number);

                continue;
            }

            try {
                $products[] = $this->mapper->map($line->record);
            } catch (InvalidSourceProduct $exception) {
                $rejected[] = new RejectedRecord($code, $exception->getMessage(), $line->record, $number);

                continue;
            }
            $payloads[$code] = $line->record;
            $lines[$code] = $number;
        }

        return new ProductFeed($file->originalName, $products, $rejected, $payloads, $lines);
    }
}
