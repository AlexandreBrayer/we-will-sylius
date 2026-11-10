<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application\Import;

use App\Yack\Catalog\Domain\Import\ColumnMapping;
use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\SpreadsheetRows;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;

/**
 * Step 2 of a manual import: how the file was read (encoding, separator, columns), the lines rejected
 * and what each valid line would change in the shop, nothing written yet.
 */
final readonly class ImportPreview
{
    public function __construct(
        public ImportFile $file,
        public SpreadsheetRows $rows,
        public ColumnMapping $mapping,
        public SyncReport $report,
    ) {
    }

    /** Lines read, header aside. */
    public function lineCount(): int
    {
        return count($this->rows->rows);
    }

    /** At least one line would be applied and every required column is there. */
    public function canConfirm(): bool
    {
        return [] === $this->mapping->missing() && [] !== $this->report->changes();
    }

    /**
     * Lines that would change a product (created, updated, disabled), unchanged ones aside.
     *
     * @return list<ProductChange>
     */
    public function changes(): array
    {
        return array_values(array_filter($this->report->changes(), static fn (ProductChange $change): bool => SyncOutcome::Unchanged !== $change->outcome));
    }

    /**
     * Lines rejected, in the order of the file (checked cells first, then the catalog rules).
     *
     * @return list<RejectedRecord>
     */
    public function rejections(): array
    {
        $failures = $this->report->failures();
        usort($failures, static fn (RejectedRecord $a, RejectedRecord $b): int => ($a->line ?? PHP_INT_MAX) <=> ($b->line ?? PHP_INT_MAX));

        return $failures;
    }
}
