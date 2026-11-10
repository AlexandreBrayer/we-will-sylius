<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\ApplyProductFeed;
use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Application\SynchronizeCatalogHandler;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SourceProduct;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncStatus;
use App\Yack\Catalog\Domain\SyncTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The batch is applied by ApplyProductFeed (its own test); here, what the handler reads and what it does
 * when the source does not answer.
 *
 * @internal
 */
#[CoversClass(SynchronizeCatalogHandler::class)]
#[CoversClass(SynchronizeCatalog::class)]
final class SynchronizeCatalogHandlerTest extends TestCase
{
    private FakeProductCatalog $catalog;

    private FakeSearchIndexer $indexer;

    private RecordingLogger $logger;

    private InMemorySyncRuns $runs;

    private FakeSyncFailureNotifier $notifier;

    private MockClock $clock;

    #[\Override]
    protected function setUp(): void
    {
        $this->catalog = new FakeProductCatalog();
        $this->indexer = new FakeSearchIndexer();
        $this->logger = new RecordingLogger();
        $this->runs = new InMemorySyncRuns();
        $this->notifier = new FakeSyncFailureNotifier();
        $this->clock = new MockClock('2026-11-10 06:00:00');
    }

    public function testTheBatchReadIsAppliedAndRecordedAsARunOfTheApi(): void
    {
        $feed = new ProductFeed('lot-test', [
            self::product('YK-PAE-M08'),
            self::product('YK-BAD-TAXON'),
        ], [new RejectedRecord('YK-MALFORMED', 'Champ « label » absent ou vide.')]);

        $report = $this->handler($feed)(new SynchronizeCatalog('data/yack/api_feed_update.json', trigger: SyncTrigger::BackOffice, operator: 'admin'));

        $this->assertSame(1, $report->count(SyncOutcome::Created));
        $this->assertSame(2, $report->count(SyncOutcome::Failed));
        $this->assertSame(SyncStatus::Partial, $report->status());
        $this->assertSame(['YK-PAE-M08'], $this->catalog->applied);
        $this->assertTrue($this->catalog->committed);
        $this->assertSame([['YK-PAE-M08']], $this->indexer->refreshed);
        $this->assertSame(1, $report->runId());
        $run = $this->runs->get(1);
        $this->assertSame([SyncSource::Api, SyncTrigger::BackOffice, 'admin', false, '2026-11-10 06:00:00'], [$run?->getSource(), $run?->getTrigger(), $run?->getOperator(), $run?->isDryRun(), $run?->getStartedAt()->format('Y-m-d H:i:s')]);
        $this->assertSame('catalog_sync.started', $this->logger->messages[0]);
        $this->assertSame('catalog_sync.finished', end($this->logger->messages));
    }

    public function testASimulationIsCarriedByTheContextOfTheCommand(): void
    {
        $command = new SynchronizeCatalog('data/yack/catalog.json', dryRun: true, recorded: false);

        $this->assertSame(
            [SyncSource::Api, SyncTrigger::Console, null, true, null, false],
            [$command->context()->source, $command->context()->trigger, $command->context()->operator, $command->context()->dryRun, $command->context()->replayOf, $command->context()->recorded],
        );
        $report = $this->handler(new ProductFeed('lot-test', [self::product('YK-PAE-M08')]))($command);

        $this->assertTrue($report->dryRun);
        $this->assertSame(['YK-PAE-M08'], $this->catalog->checked);
        $this->assertSame([], $this->runs->runs);
    }

    public function testAnUnavailableSourceGivesAFailedReportAndAFailedRunInsteadOfAnException(): void
    {
        $report = $this->handler(null)(new SynchronizeCatalog('data/yack/absent.json'));

        $this->assertSame(SyncStatus::Failure, $report->status());
        $this->assertSame('Flux Yack « data/yack/absent.json » illisible.', $report->error());
        $this->assertSame('absent.json', $report->origin);
        $this->assertSame(0, $report->total());
        $this->assertFalse($this->catalog->committed);
        $this->assertSame([], $this->indexer->refreshed);
        $this->assertSame(1, $report->runId());
        $this->assertSame('Flux Yack « data/yack/absent.json » illisible.', $this->runs->get(1)?->getError());
        $this->assertSame('catalogue@yack.test', $this->runs->get(1)->getNotifiedTo());
        $this->assertSame(['info', 'critical'], $this->logger->levels);
        $this->assertSame('catalog_sync.source_unavailable', $this->logger->messages[1]);
    }

    private function handler(?ProductFeed $feed): SynchronizeCatalogHandler
    {
        $source = $this->createStub(ProductSource::class);
        null === $feed
            ? $source->method('fetch')->willThrowException(new ProductSourceUnavailable('Flux Yack « data/yack/absent.json » illisible.'))
            : $source->method('fetch')->willReturn($feed);

        return new SynchronizeCatalogHandler(
            $source,
            new ApplyProductFeed($this->catalog, $this->runs, $this->indexer, $this->notifier, $this->logger, $this->clock),
            $this->logger,
            $this->clock,
        );
    }

    private static function product(string $code): SourceProduct
    {
        return new SourceProduct($code, 'Produit ' . $code, true, ProductKind::Finished, 'pac_air_eau', 649000, 3);
    }
}
