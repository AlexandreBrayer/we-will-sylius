<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application\Import;

use App\Yack\Catalog\Application\Import\ImportPreview;
use App\Yack\Catalog\Domain\Import\ColumnMapping;
use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use App\Yack\Catalog\Domain\Import\SpreadsheetRows;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ImportPreview::class)]
final class ImportPreviewTest extends TestCase
{
    public function testTheChangesLeaveTheUnchangedLinesOutAndTheRejectionsFollowTheFile(): void
    {
        $report = new SyncReport('tarifs.csv', dryRun: true);
        $report->record($created = new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created));
        $report->record(new ProductChange('YK-2', 'Produit 2', SyncOutcome::Unchanged));
        $report->record($updated = new ProductChange('YK-3', 'Produit 3', SyncOutcome::Updated));
        $report->fail($line9 = new RejectedRecord('YK-9', 'Prix absent.', line: 9));
        $report->fail($noLine = new RejectedRecord('YK-X', 'Catégorie inconnue.'));
        $report->fail($line4 = new RejectedRecord('YK-4', 'Stock illisible.', line: 4));

        $preview = self::preview(self::allColumns(), $report);

        $this->assertSame(3, $preview->lineCount());
        $this->assertTrue($preview->canConfirm());
        $this->assertSame([$created, $updated], $preview->changes());
        $this->assertSame([$line4, $line9, $noLine], $preview->rejections());
    }

    public function testNothingToApplyOrAMissingColumnCannotBeConfirmed(): void
    {
        $this->assertFalse(self::preview(self::allColumns(), new SyncReport('tarifs.csv', dryRun: true))->canConfirm());

        $report = new SyncReport('tarifs.csv', dryRun: true);
        $report->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created));
        $this->assertFalse(self::preview(['Code'], $report)->canConfirm());
    }

    /** @param list<string> $headers */
    private static function preview(array $headers, SyncReport $report): ImportPreview
    {
        return new ImportPreview(
            new ImportFile(str_repeat('ab', 16), '/tmp/x.csv', 'tarifs.csv', ImportFormat::Csv, 10),
            new SpreadsheetRows($headers, [2 => ['a'], 3 => ['b'], 5 => ['c']]),
            ColumnMapping::detect($headers),
            $report,
        );
    }

    /** @return list<string> */
    private static function allColumns(): array
    {
        return array_map(static fn (ImportColumn $column): string => $column->header(), ImportColumn::cases());
    }
}
