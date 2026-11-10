<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application\Import;

use App\Tests\Yack\Catalog\Application\FakeProductCatalog;
use App\Tests\Yack\Catalog\Application\FakeSearchIndexer;
use App\Tests\Yack\Catalog\Application\FakeSyncFailureNotifier;
use App\Tests\Yack\Catalog\Application\InMemorySyncRuns;
use App\Tests\Yack\Catalog\Application\RecordingLogger;
use App\Yack\Catalog\Application\ApplyProductFeed;
use App\Yack\Catalog\Application\Import\ImportCatalogFile;
use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use App\Yack\Catalog\Domain\Import\SpreadsheetReader;
use App\Yack\Catalog\Domain\Import\SpreadsheetRows;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncTrigger;
use App\Yack\Catalog\Infrastructure\Source\FeedRecordMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Each line goes through the real mapping of the API feed (FeedRecordMapper) and the real use case
 * (ApplyProductFeed) on fakes of the ports.
 *
 * @internal
 */
#[CoversClass(ImportCatalogFile::class)]
final class ImportCatalogFileTest extends TestCase
{
    private const HEADERS = ['Code', 'Nom', 'Nature', 'Actif', 'Catégorie', 'Prix HT (€)', 'Stock', 'Marque'];

    private InMemoryImportFiles $files;

    private FakeProductCatalog $catalog;

    private InMemorySyncRuns $runs;

    #[\Override]
    protected function setUp(): void
    {
        $this->files = new InMemoryImportFiles();
        $this->catalog = new FakeProductCatalog();
        $this->runs = new InMemorySyncRuns();
    }

    public function testTheFileSentIsStoredAndFoundBackByItsToken(): void
    {
        $import = $this->import(new SpreadsheetRows([], []));

        $file = $import->store('/tmp/php123', 'tarifs.csv');

        $this->assertSame($file, $import->file($file->token));
        $this->assertNull($import->file(str_repeat('0', 32)));
        $this->assertNull($import->file('../../etc/passwd'));
        $this->assertSame([$file->token, str_repeat('0', 32)], $this->files->found);
    }

    public function testThePreviewRejectsEachFaultyLineWithItsReasonAndWritesNothing(): void
    {
        $file = $this->files->store('/tmp/php123', 'tarifs.csv');

        $preview = $this->import(self::rows())->preview($file);

        $this->assertSame($file, $preview->file);
        $this->assertSame([], $preview->mapping->missing());
        $this->assertSame(['SRK25ZS-W'], array_map(static fn (ProductChange $change): string => $change->code, $preview->report->changes()));
        $this->assertSame(SyncOutcome::Created, $preview->report->changes()[0]->outcome);
        $this->assertSame([
            ['SRK25ZS-W', 'code déjà présent ligne 2 : seule la première ligne est importée.', 3],
            ['Ligne 4', 'colonne « Code » vide.', 4],
            ['YK-STOCK', 'colonne « Stock » : nombre attendu (« beaucoup »).', 5],
            ['YK-NEG', "Produit « YK-NEG » : prix négatif (-5,00\u{a0}€).", 6],
        ], array_map(static fn (RejectedRecord $failure): array => [$failure->reference, $failure->reason, $failure->line], $preview->report->failures()));
        $this->assertSame('beaucoup', $preview->report->failures()[2]->payload['stock'] ?? null);
        $this->assertSame([], $this->catalog->applied);
        $this->assertSame([], $this->runs->runs);
        $this->assertSame([], $this->files->deleted);
    }

    public function testAFileWithoutTheRequiredColumnsIsPreviewedWithoutAnyLine(): void
    {
        $file = $this->files->store('/tmp/php123', 'tarifs.csv');

        $preview = $this->import(new SpreadsheetRows(['Code', 'Nom'], [2 => ['SRK25ZS-W', 'Console']]))->preview($file);

        $this->assertSame([ImportColumn::Kind, ImportColumn::Active, ImportColumn::Category, ImportColumn::Price, ImportColumn::Stock], $preview->mapping->missing());
        $this->assertSame(['tarifs.csv', 0], [$preview->report->origin, $preview->report->total()]);
    }

    public function testTheConfirmationAppliesTheFileAsARunOfTheImportThenDeletesIt(): void
    {
        $file = $this->files->store('/tmp/php123', 'tarifs.csv');

        $report = $this->import(self::rows())->confirm($file, 'admin');

        $this->assertSame(['SRK25ZS-W'], $this->catalog->applied);
        $this->assertSame([1, 4], [$report->count(SyncOutcome::Created), $report->count(SyncOutcome::Failed)]);
        $run = $this->runs->get((int) $report->runId());
        $this->assertSame([SyncSource::Csv, SyncTrigger::BackOffice, 'admin', 'tarifs.csv'], [$run?->getSource(), $run?->getTrigger(), $run?->getOperator(), $run?->getOrigin()]);
        $this->assertSame([3, 4, 5, 6], array_map(static fn ($line): ?int => $line->getFileLine(), $this->runs->rejections($run)));
        $this->assertSame([$file->token], $this->files->deleted);
    }

    public function testAFileWithoutTheRequiredColumnsIsNotConfirmed(): void
    {
        $file = $this->files->store('/tmp/php123', 'tarifs.csv');

        try {
            $this->import(new SpreadsheetRows(['Code', 'Nom', 'Prix'], []))->confirm($file, 'admin');
            $this->fail('A file without its required columns must be refused.');
        } catch (ImportFileRefused $exception) {
            $this->assertSame('Colonnes obligatoires absentes : Nature, Actif, Catégorie, Stock. Téléchargez le modèle pour voir les en-têtes attendus.', $exception->getMessage());
        }
        $this->assertSame([], $this->files->deleted);
        $this->assertSame([], $this->runs->runs);
    }

    public function testACancelledImportDeletesTheFile(): void
    {
        $file = new ImportFile(str_repeat('ab', 16), '/tmp/x.xlsx', 'catalogue.xlsx', ImportFormat::Xlsx, 10);

        $this->import(new SpreadsheetRows([], []))->cancel($file);

        $this->assertSame([$file->token], $this->files->deleted);
    }

    private function import(SpreadsheetRows $rows): ImportCatalogFile
    {
        $reader = $this->createStub(SpreadsheetReader::class);
        $reader->method('read')->willReturn($rows);
        $clock = new MockClock('2026-11-10 06:00:00');

        return new ImportCatalogFile(
            $this->files,
            $reader,
            new FeedRecordMapper(),
            new ApplyProductFeed($this->catalog, $this->runs, new FakeSearchIndexer(), new FakeSyncFailureNotifier(), new RecordingLogger(), $clock),
        );
    }

    private static function rows(): SpreadsheetRows
    {
        return new SpreadsheetRows(self::HEADERS, [
            2 => ['SRK25ZS-W', 'Console murale', 'Produit fini', 'oui', 'pac_air_air', '1 699,90', '12', 'MHI'],
            3 => ['SRK25ZS-W', 'Doublon', 'Produit fini', 'oui', 'pac_air_air', '10', '1'],
            4 => ['', 'Sans code', 'Produit fini', 'oui', 'pac_air_air', '10', '1'],
            5 => ['YK-STOCK', 'Stock illisible', 'Pièce détachée', 'non', 'pieces', '10', 'beaucoup'],
            6 => ['YK-NEG', 'Prix négatif', 'Pièce détachée', 'non', 'pieces', '-5', '1'],
        ], 'UTF-8', ';');
    }
}
