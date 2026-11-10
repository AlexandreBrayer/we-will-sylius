<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Persistence;

use App\Entity\Catalog\SyncRun;
use App\Entity\Catalog\SyncRunLine;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncRunFilter;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncStatus;
use App\Yack\Catalog\Infrastructure\Persistence\DoctrineSyncRuns;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Real Doctrine ORM on an in-memory SQLite database, mapping of the entities of the journal only.
 *
 * @internal
 */
#[CoversClass(DoctrineSyncRuns::class)]
final class DoctrineSyncRunsTest extends TestCase
{
    private const NOW = '2026-11-10 12:00:00';

    private EntityManager $entityManager;

    private DoctrineSyncRuns $runs;

    #[\Override]
    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 5) . '/src/Entity/Catalog'], true);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(SyncRun::class),
            $this->entityManager->getClassMetadata(SyncRunLine::class),
        ]);
        $this->runs = new DoctrineSyncRuns($this->entityManager);
    }

    public function testARunIsStoredWithItsLinesAndFoundBackByItsId(): void
    {
        $run = $this->add('2026-11-10 06:00', created: 2, rejected: 1);

        $this->assertSame(1, $run->getId());
        $this->entityManager->clear();
        $found = $this->runs->get(1);

        $this->assertNotSame($run, $found);
        $this->assertSame([SyncStatus::Partial, 3, ['YK-0', 'YK-1', 'YK-BAD-0']], [$found?->getStatus(), $found?->total(), array_map(static fn (SyncRunLine $line): string => $line->getCode(), $found?->getLines() ?? [])]);
        $this->assertSame(['sku' => 'YK-BAD-0'], $found?->getLines()[2]->getPayload());
        $this->assertNull($this->runs->get(99));
    }

    public function testAChangeOfAStoredRunIsSaved(): void
    {
        $run = SyncRun::failed(new SyncContext(), 'catalog.json', 'API injoignable.', new \DateTimeImmutable('2026-11-10 06:00'), new \DateTimeImmutable('2026-11-10 06:00'));
        $this->runs->add($run);

        $run->notified('catalogue@yack.test');
        $this->runs->save($run);
        $this->entityManager->clear();

        $this->assertSame('catalogue@yack.test', $this->runs->get((int) $run->getId())?->getNotifiedTo());
    }

    /** @param list<int> $ids */
    #[DataProvider('provideFilters')]
    public function testTheJournalIsListedNewestFirstWithTheFilter(SyncRunFilter $filter, array $ids): void
    {
        $this->journal();

        $page = $this->runs->page($filter, new \DateTimeImmutable(self::NOW), 1, 10);

        $this->assertSame([$ids, count($ids)], [array_map(static fn (SyncRun $run): ?int => $run->getId(), $page->items), $page->total]);
    }

    /** @return \Iterator<string, array{SyncRunFilter, list<int>}> */
    public static function provideFilters(): \Iterator
    {
        yield 'tout' => [new SyncRunFilter(), [5, 4, 3, 2, 1]];
        yield 'en échec' => [new SyncRunFilter(SyncStatus::Failure), [3]];
        yield 'imports csv' => [new SyncRunFilter(source: SyncSource::Csv), [4]];
        yield 'simulations' => [new SyncRunFilter(simulationsOnly: true), [5]];
        yield 'dernières 24 heures' => [new SyncRunFilter(days: 1), [5, 4, 3]];
    }

    public function testTheJournalIsListedByPage(): void
    {
        $this->journal();

        $page = $this->runs->page(new SyncRunFilter(), new \DateTimeImmutable(self::NOW), 2, 2);

        $this->assertSame([[3, 2], 5, 2, 2], [array_map(static fn (SyncRun $run): ?int => $run->getId(), $page->items), $page->total, $page->page, $page->perPage]);
    }

    public function testTheRunsSinceADateLeaveTheSimulationsOutOldestFirst(): void
    {
        $this->journal();

        $this->assertSame([2, 3, 4], array_map(static fn (SyncRun $run): ?int => $run->getId(), $this->runs->since(new \DateTimeImmutable('2026-11-09 00:00'))));
    }

    public function testTheLatestRunAndTheLatestSuccessLeaveTheSimulationsOut(): void
    {
        $this->assertSame([null, null], [$this->runs->latest(), $this->runs->latestSuccess()]);

        $this->journal();

        $this->assertSame([4, 2], [$this->runs->latest()?->getId(), $this->runs->latestSuccess()?->getId()]);
    }

    public function testTheLinesOfARunByOutcomeAndByPage(): void
    {
        $run = $this->add('2026-11-10 06:00', created: 3, rejected: 2);

        $all = $this->runs->lines($run, null, 2, 2);
        $rejected = $this->runs->lines($run, SyncOutcome::Failed, 1, 10);

        $this->assertSame([['YK-2', 'YK-BAD-0'], 5], [array_map(static fn (SyncRunLine $line): string => $line->getCode(), $all->items), $all->total]);
        $this->assertSame([['YK-BAD-0', 'YK-BAD-1'], 2], [array_map(static fn (SyncRunLine $line): string => $line->getCode(), $rejected->items), $rejected->total]);
        $this->assertSame(['YK-BAD-0', 'YK-BAD-1'], array_map(static fn (SyncRunLine $line): string => $line->getCode(), $this->runs->rejections($run)));
    }

    public function testTheRejectionsOfAnImportFollowTheLinesOfTheFileThoseWithoutLineLast(): void
    {
        $report = new SyncReport('tarifs.csv');
        $report->fail(new RejectedRecord('YK-SANS', 'Code en double.'));
        $report->fail(new RejectedRecord('YK-L5', 'Prix négatif.', [], 5));
        $report->fail(new RejectedRecord('YK-L2', 'Nom absent.', [], 2));
        $run = SyncRun::completed($report, new SyncContext(SyncSource::Csv), new \DateTimeImmutable('2026-11-10 06:00'), new \DateTimeImmutable('2026-11-10 06:00'));
        $this->runs->add($run);

        $codes = static fn (array $lines): array => array_map(static fn (SyncRunLine $line): string => $line->getCode(), $lines);
        $this->assertSame(
            [['YK-L2', 'YK-L5', 'YK-SANS'], ['YK-L2', 'YK-L5', 'YK-SANS']],
            [$codes($this->runs->rejections($run)), $codes($this->runs->lines($run, null, 1, 10)->items)],
        );
    }

    public function testTheReplaysOfARunOldestFirst(): void
    {
        $run = $this->add('2026-11-10 06:00', created: 0, rejected: 2);
        $other = $this->add('2026-11-10 06:30', created: 0, rejected: 1);
        $late = $this->add('2026-11-10 09:00', created: 1, rejected: 0, context: new SyncContext(SyncSource::Replay, replayOf: $run->getId()));
        $early = $this->add('2026-11-10 08:00', created: 1, rejected: 0, context: new SyncContext(SyncSource::Replay, replayOf: $run->getId()));

        $this->assertSame([$early->getId(), $late->getId()], array_map(static fn (SyncRun $replay): ?int => $replay->getId(), $this->runs->replaysOf($run)));
        $this->assertSame([], $this->runs->replaysOf($other));
    }

    /** Five runs: 1 partial (8th), 2 success (9th), 3 failure, 4 partial CSV import, 5 simulation (10th). */
    private function journal(): void
    {
        $this->add('2026-11-08 06:00', created: 1, rejected: 1);
        $this->add('2026-11-09 06:00', created: 2, rejected: 0);
        $this->runs->add(SyncRun::failed(new SyncContext(), 'catalog.json', 'API injoignable.', new \DateTimeImmutable('2026-11-10 06:00'), new \DateTimeImmutable('2026-11-10 06:00')));
        $this->add('2026-11-10 06:30', created: 1, rejected: 1, context: new SyncContext(SyncSource::Csv));
        $this->add('2026-11-10 07:00', created: 1, rejected: 0, context: new SyncContext(dryRun: true));
    }

    private function add(string $at, int $created, int $rejected, SyncContext $context = new SyncContext()): SyncRun
    {
        $report = new SyncReport('lot', $context->dryRun);
        for ($i = 0; $i < $created; ++$i) {
            $report->record(new ProductChange('YK-' . $i, 'Produit ' . $i, SyncOutcome::Created));
        }
        for ($i = 0; $i < $rejected; ++$i) {
            $report->fail(new RejectedRecord('YK-BAD-' . $i, 'Prix absent.', ['sku' => 'YK-BAD-' . $i]));
        }
        $run = SyncRun::completed($report, $context, new \DateTimeImmutable($at), new \DateTimeImmutable($at));
        $this->runs->add($run);

        return $run;
    }
}
