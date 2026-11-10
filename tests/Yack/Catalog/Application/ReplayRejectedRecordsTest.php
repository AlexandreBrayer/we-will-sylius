<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Entity\Catalog\SyncRun;
use App\Entity\Catalog\SyncRunLine;
use App\Yack\Catalog\Application\ApplyProductFeed;
use App\Yack\Catalog\Application\ReplayRejectedRecords;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncTrigger;
use App\Yack\Catalog\Infrastructure\Source\FeedRecordMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The records go through the real mapping of the API feed (FeedRecordMapper) and the real use case
 * (ApplyProductFeed) on fakes of the ports.
 *
 * @internal
 */
#[CoversClass(ReplayRejectedRecords::class)]
final class ReplayRejectedRecordsTest extends TestCase
{
    private InMemorySyncRuns $runs;

    private FakeProductCatalog $catalog;

    private ReplayRejectedRecords $replay;

    #[\Override]
    protected function setUp(): void
    {
        $this->runs = new InMemorySyncRuns();
        $this->catalog = new FakeProductCatalog();
        $this->replay = new ReplayRejectedRecords(
            $this->runs,
            new FeedRecordMapper(),
            new ApplyProductFeed($this->catalog, $this->runs, new FakeSearchIndexer(), new FakeSyncFailureNotifier(), new RecordingLogger(), new MockClock('2026-11-10 06:00:00')),
        );
    }

    public function testOnlyTheRejectionsWithARecordNotYetAppliedByAReplayCanBeReplayed(): void
    {
        $run = $this->rejectingRun([
            new RejectedRecord('YK-SETTLED', 'Prix négatif.', self::record('YK-SETTLED')),
            new RejectedRecord('#2', 'Pas un objet.'),
            new RejectedRecord('YK-STILL', 'Prix négatif.', self::record('YK-STILL')),
        ]);
        $replay = new SyncReport('Rejets');
        $replay->record(new ProductChange('YK-SETTLED', 'Produit', SyncOutcome::Created));
        $replay->fail(new RejectedRecord('YK-STILL', 'Prix négatif.', self::record('YK-STILL')));
        $this->runs->add(SyncRun::completed($replay, new SyncContext(SyncSource::Replay, replayOf: $run->getId()), new \DateTimeImmutable('2026-11-10 07:00'), new \DateTimeImmutable('2026-11-10 07:00')));

        $this->assertSame(['YK-STILL'], array_map(static fn (SyncRunLine $line): string => $line->getCode(), $this->replay->replayable($run)));
    }

    public function testASimulationHasNothingToReplay(): void
    {
        $report = new SyncReport('lot', dryRun: true);
        $report->fail(new RejectedRecord('YK-1', 'Prix négatif.', self::record('YK-1')));
        $run = SyncRun::completed($report, new SyncContext(dryRun: true), new \DateTimeImmutable('2026-11-10 05:00'), new \DateTimeImmutable('2026-11-10 05:00'));
        $this->runs->add($run);

        $this->assertSame([], $this->replay->replayable($run));
    }

    public function testTheCorrectedRecordsAreAppliedInANewRunLinkedToTheFirstOne(): void
    {
        $run = $this->rejectingRun([
            new RejectedRecord('YK-PRICE', 'Prix négatif.', self::record('YK-PRICE'), 4),
            new RejectedRecord('YK-STOCK', 'Prix négatif.', self::record('YK-STOCK'), 5),
            new RejectedRecord('YK-KIND', 'Nature inconnue.', ['type' => 'kit'] + self::record('YK-KIND')),
            new RejectedRecord('YK-ASIS', 'Catégorie inconnue.', ['price' => ['amount' => '10', 'currency' => 'EUR']] + self::record('YK-ASIS')),
        ]);
        [$price, $stock, $kind] = $this->runs->rejections($run);

        $report = ($this->replay)($run, [
            (int) $price->getId() => ['prix_ht' => '649,00', 'code' => 'PIRATE', 'nom' => 'Pompe corrigée'],
            (int) $stock->getId() => ['prix_ht' => '10', 'stock' => 'beaucoup'],
            (int) $kind->getId() => ['prix_ht' => '10'],
        ], 'admin');

        $this->assertSame(['YK-PRICE', 'YK-ASIS'], $this->catalog->applied);
        $this->assertSame([2, 2], [$report->count(SyncOutcome::Created), $report->count(SyncOutcome::Failed)]);
        $this->assertSame('Rejets de la synchronisation n° 1', $report->origin);
        $this->assertSame(
            [['YK-STOCK', 'colonne « Stock » : nombre attendu (« beaucoup »).', 5], ['YK-KIND', 'Produit « YK-KIND » : nature « kit » inconnue.', null]],
            array_map(static fn (RejectedRecord $failure): array => [$failure->reference, $failure->reason, $failure->line], $report->failures()),
        );
        $this->assertSame('beaucoup', $report->failures()[0]->payload['stock'] ?? null);

        $replay = $this->runs->get((int) $report->runId());
        $this->assertSame([SyncSource::Replay, SyncTrigger::BackOffice, 'admin', 1], [$replay?->getSource(), $replay?->getTrigger(), $replay?->getOperator(), $replay?->getReplayOf()]);
        $this->assertSame('Pompe corrigée', $replay->getLines()[0]->getName());
        $this->assertSame(['YK-STOCK', 'YK-KIND'], array_map(static fn (SyncRunLine $line): string => $line->getCode(), $this->replay->replayable($run)));
    }

    public function testARunWithoutRecordToReplayIsRefused(): void
    {
        $run = $this->rejectingRun([new RejectedRecord('#1', 'Pas un objet.')]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Run 1 has no rejected record to replay.');

        ($this->replay)($run, [], null);
    }

    /** @param list<RejectedRecord> $failures */
    private function rejectingRun(array $failures): SyncRun
    {
        $report = new SyncReport('lot');
        foreach ($failures as $failure) {
            $report->fail($failure);
        }
        $run = SyncRun::completed($report, new SyncContext(), new \DateTimeImmutable('2026-11-10 05:00'), new \DateTimeImmutable('2026-11-10 05:00'));
        $this->runs->add($run);

        return $run;
    }

    /** @return array<string, mixed> */
    private static function record(string $sku): array
    {
        return ['sku' => $sku, 'label' => 'Pompe ' . $sku, 'type' => 'finished', 'status' => 'active', 'category' => 'pac_air_eau', 'price' => ['amount' => '-5', 'currency' => 'EUR'], 'stock' => 3];
    }
}
