<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\ApplyProductFeed;
use App\Yack\Catalog\Domain\CatalogProduct;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SourceProduct;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncStatus;
use App\Yack\Catalog\Domain\SyncTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(ApplyProductFeed::class)]
#[CoversClass(ProductFeed::class)]
#[CoversClass(SyncContext::class)]
final class ApplyProductFeedTest extends TestCase
{
    private FakeProductCatalog $catalog;

    private InMemorySyncRuns $runs;

    private FakeSearchIndexer $indexer;

    private FakeSyncFailureNotifier $notifier;

    private RecordingLogger $logger;

    private MockClock $clock;

    #[\Override]
    protected function setUp(): void
    {
        $this->catalog = new FakeProductCatalog([
            'YK-PRICE' => self::current('YK-PRICE', priceInCents: 590000),
            'YK-OLD' => self::current('YK-OLD'),
            'YK-SAME' => self::current('YK-SAME'),
            'YK-BAD-TAXON' => self::current('YK-BAD-TAXON', priceInCents: 1),
        ]);
        $this->runs = new InMemorySyncRuns();
        $this->indexer = new FakeSearchIndexer();
        $this->notifier = new FakeSyncFailureNotifier();
        $this->logger = new RecordingLogger();
        $this->clock = new MockClock('2026-11-10 06:00:00');
    }

    public function testEachRecordIsComparedWithTheShopThenWrittenRecordedAndIndexed(): void
    {
        $report = $this->apply()($this->feed(), new SyncContext(SyncSource::Csv, SyncTrigger::BackOffice, 'admin'), new \DateTimeImmutable('2026-11-10 05:59:58'));

        $this->assertSame(['created' => 1, 'updated' => 1, 'disabled' => 1, 'unchanged' => 1, 'failed' => 2], $report->toArray());
        $this->assertSame(
            ['YK-NEW' => SyncOutcome::Created, 'YK-PRICE' => SyncOutcome::Updated, 'YK-OLD' => SyncOutcome::Disabled, 'YK-SAME' => SyncOutcome::Unchanged],
            array_combine(array_map(static fn (ProductChange $change): string => $change->code, $report->changes()), array_map(static fn (ProductChange $change): SyncOutcome => $change->outcome, $report->changes())),
        );
        // An unchanged product is not written; a product the shop refuses keeps its record and its line.
        $this->assertSame(['YK-NEW', 'YK-PRICE', 'YK-OLD'], $this->catalog->applied);
        $this->assertSame([], $this->catalog->checked);
        $failure = $report->failures()[1];
        $this->assertSame(['YK-BAD-TAXON', 'Produit « YK-BAD-TAXON » : catégorie ou marque « nope » inconnue.', ['sku' => 'YK-BAD-TAXON'], 7], [$failure->reference, $failure->reason, $failure->payload, $failure->line]);
        $this->assertSame('YK-MALFORMED', $report->failures()[0]->reference);
        $this->assertTrue($this->catalog->committed);
        $this->assertSame([['YK-NEW', 'YK-PRICE', 'YK-OLD']], $this->indexer->refreshed);

        $this->assertSame(1, $report->runId());
        $run = $this->runs->get(1);
        $this->assertSame([SyncStatus::Partial, SyncSource::Csv, 'admin', 2000, 6], [$run?->getStatus(), $run?->getSource(), $run?->getOperator(), $run?->getDurationMs(), count($run?->getLines() ?? [])]);
        $this->assertSame(
            ['catalog_sync.record_rejected', 'catalog_sync.product_created', 'catalog_sync.product_updated', 'catalog_sync.product_disabled', 'catalog_sync.product_unchanged', 'catalog_sync.record_rejected', 'catalog_sync.finished'],
            $this->logger->messages,
        );
        $this->assertSame(['error', 'info', 'info', 'info', 'info', 'error', 'info'], $this->logger->levels);
    }

    public function testASimulationChecksTheRecordsWithoutWritingButIsRecorded(): void
    {
        $report = $this->apply()($this->feed(), new SyncContext(dryRun: true));

        $this->assertTrue($report->dryRun);
        $this->assertSame(['YK-NEW', 'YK-PRICE', 'YK-OLD'], $this->catalog->checked);
        $this->assertSame([], $this->catalog->applied);
        $this->assertFalse($this->catalog->committed);
        $this->assertSame([], $this->indexer->refreshed);
        $this->assertTrue($this->runs->get(1)?->isDryRun());
        $this->assertSame('2026-11-10 06:00:00', $this->runs->get(1)->getStartedAt()->format('Y-m-d H:i:s'));
        $this->assertSame(0, $this->runs->get(1)->written());
        $this->assertSame('catalog_sync.finished', end($this->logger->messages));
    }

    public function testAPreviewWritesNothingAndLeavesNoTrace(): void
    {
        $report = $this->apply()->preview($this->feed());

        $this->assertSame(1, $report->count(SyncOutcome::Created));
        $this->assertNull($report->runId());
        $this->assertSame([], $this->catalog->applied);
        $this->assertSame([], $this->runs->runs);
        $this->assertSame([], $this->logger->messages);
    }

    public function testTheSeedOfTheDemoCatalogWritesAndLogsButIsNotRecorded(): void
    {
        $report = $this->apply()(new ProductFeed('catalog.json', [self::source('YK-NEW')]), new SyncContext(recorded: false));

        $this->assertSame(['YK-NEW'], $this->catalog->applied);
        $this->assertNull($report->runId());
        $this->assertSame([], $this->runs->runs);
        $this->assertSame(['catalog_sync.product_created', 'catalog_sync.finished'], $this->logger->messages);
    }

    public function testAnUnreadBatchIsRecordedAsAFailureAndTheAdministratorsAlerted(): void
    {
        $this->clock->modify('+30 seconds');
        $report = $this->apply()->recordFailure(new SyncContext(), 'catalog.json', 'API injoignable.', new \DateTimeImmutable('2026-11-10 06:00:00'));

        $this->assertSame([SyncStatus::Failure, 'API injoignable.', 1], [$report->status(), $report->error(), $report->runId()]);
        $run = $this->runs->get(1);
        $this->assertSame([SyncStatus::Failure, 'catalog.json', 'API injoignable.', 30000, 'catalogue@yack.test'], [$run?->getStatus(), $run?->getOrigin(), $run?->getError(), $run?->getDurationMs(), $run?->getNotifiedTo()]);
        $this->assertSame([1], $this->runs->saved);
    }

    public function testAFailureNobodyIsAlertedOfIsNotSavedAgain(): void
    {
        $this->notifier = new FakeSyncFailureNotifier(null);

        $report = $this->apply()->recordFailure(new SyncContext(), 'catalog.json', 'API injoignable.', $this->clock->now());

        $this->assertSame(1, $report->runId());
        $this->assertCount(1, $this->notifier->notified);
        $this->assertNull($this->runs->get(1)?->getNotifiedTo());
        $this->assertSame([], $this->runs->saved);
    }

    public function testAFailedSimulationAlertsNobody(): void
    {
        $report = $this->apply()->recordFailure(new SyncContext(dryRun: true), 'catalog.json', 'API injoignable.', $this->clock->now());

        $this->assertSame(1, $report->runId());
        $this->assertTrue($this->runs->get(1)?->isDryRun());
        $this->assertSame([], $this->notifier->notified);
    }

    public function testAFailureLeftOutOfTheJournalIsOnlyReported(): void
    {
        $report = $this->apply()->recordFailure(new SyncContext(recorded: false), 'catalog.json', 'API injoignable.', $this->clock->now());

        $this->assertSame(['API injoignable.', null], [$report->error(), $report->runId()]);
        $this->assertSame([], $this->runs->runs);
        $this->assertSame([], $this->notifier->notified);
    }

    private function apply(): ApplyProductFeed
    {
        return new ApplyProductFeed($this->catalog, $this->runs, $this->indexer, $this->notifier, $this->logger, $this->clock);
    }

    private function feed(): ProductFeed
    {
        return new ProductFeed(
            'lot-test',
            [self::source('YK-NEW'), self::source('YK-PRICE'), self::source('YK-OLD', active: false), self::source('YK-SAME'), self::source('YK-BAD-TAXON')],
            [new RejectedRecord('YK-MALFORMED', 'Champ « label » absent ou vide.', ['sku' => 'YK-MALFORMED'])],
            ['YK-BAD-TAXON' => ['sku' => 'YK-BAD-TAXON']],
            ['YK-BAD-TAXON' => 7],
        );
    }

    private static function source(string $code, bool $active = true): SourceProduct
    {
        return new SourceProduct($code, 'Produit ' . $code, $active, ProductKind::Finished, 'pac_air_eau', 649000, 3);
    }

    private static function current(string $code, int $priceInCents = 649000): CatalogProduct
    {
        return new CatalogProduct($code, 'Produit ' . $code, true, $priceInCents, 3, 'pac_air_eau', [ProductChange::KIND_ATTRIBUTE => 'Produit fini']);
    }
}
