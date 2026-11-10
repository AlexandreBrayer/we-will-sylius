<?php

declare(strict_types=1);

namespace App\Tests\Entity\Catalog;

use App\Entity\Catalog\SyncRun;
use App\Entity\Catalog\SyncRunLine;
use App\Yack\Catalog\Domain\FieldChange;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncStatus;
use App\Yack\Catalog\Domain\SyncTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncRun::class)]
final class SyncRunTest extends TestCase
{
    public function testACompletedRunKeepsItsCountersAndOneLinePerRecordRejectionsLast(): void
    {
        $report = new SyncReport('tarifs.csv');
        $report->fail(new RejectedRecord('YK-BAD', 'Prix absent.', ['sku' => 'YK-BAD'], 3));
        $report->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created, [new FieldChange('name', 'Nom', null, 'Produit 1')]));
        $report->record(new ProductChange('YK-2', 'Produit 2', SyncOutcome::Updated));
        $report->record(new ProductChange('YK-3', 'Produit 3', SyncOutcome::Disabled));
        $report->record(new ProductChange('YK-4', 'Produit 4', SyncOutcome::Unchanged));
        $context = new SyncContext(SyncSource::Csv, SyncTrigger::BackOffice, str_repeat('a', 300), replayOf: 4);

        $run = SyncRun::completed($report, $context, new \DateTimeImmutable('2026-11-10 06:00:00.250'), new \DateTimeImmutable('2026-11-10 06:00:01.500'));

        $this->assertSame(
            [null, SyncSource::Csv, SyncTrigger::BackOffice, 255, false, 4, 'tarifs.csv', 1250, SyncStatus::Partial, null, null],
            [$run->getId(), $run->getSource(), $run->getTrigger(), mb_strlen((string) $run->getOperator()), $run->isDryRun(), $run->getReplayOf(), $run->getOrigin(), $run->getDurationMs(), $run->getStatus(), $run->getError(), $run->getNotifiedTo()],
        );
        $this->assertSame(['2026-11-10 06:00:00', '2026-11-10 06:00:01'], [$run->getStartedAt()->format('Y-m-d H:i:s'), $run->getFinishedAt()->format('Y-m-d H:i:s')]);
        $this->assertSame(['created' => 1, 'updated' => 1, 'disabled' => 1, 'unchanged' => 1, 'failed' => 1], $run->counts());
        $this->assertSame([5, 3], [$run->total(), $run->written()]);
        $this->assertSame(
            [[1, 'YK-1', SyncOutcome::Created], [2, 'YK-2', SyncOutcome::Updated], [3, 'YK-3', SyncOutcome::Disabled], [4, 'YK-4', SyncOutcome::Unchanged], [5, 'YK-BAD', SyncOutcome::Failed]],
            array_map(static fn (SyncRunLine $line): array => [$line->getPosition(), $line->getCode(), $line->getOutcome()], $run->getLines()),
        );
        $this->assertSame($run, $run->getLines()[0]->getRun());
    }

    public function testASimulationWritesNothing(): void
    {
        $report = new SyncReport('catalog.json', dryRun: true);
        $report->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created));

        $run = SyncRun::completed($report, new SyncContext(dryRun: true), new \DateTimeImmutable('2026-11-10 06:00'), new \DateTimeImmutable('2026-11-10 06:00'));

        $this->assertSame([true, 1, 0, SyncStatus::Success, null], [$run->isDryRun(), $run->total(), $run->written(), $run->getStatus(), $run->getOperator()]);
    }

    public function testAFailedRunKeepsWhyAndWhoWasAlerted(): void
    {
        $run = SyncRun::failed(new SyncContext(), str_repeat('é', 300), 'API injoignable.', new \DateTimeImmutable('2026-11-10 06:00:30'), new \DateTimeImmutable('2026-11-10 06:00:00'));
        $run->notified('catalogue@yack.test');

        $this->assertSame(
            [SyncStatus::Failure, 'API injoignable.', 'catalogue@yack.test', 255, 0, 0, [], SyncSource::Api, SyncTrigger::Console],
            [$run->getStatus(), $run->getError(), $run->getNotifiedTo(), mb_strlen($run->getOrigin()), $run->getDurationMs(), $run->total(), $run->getLines(), $run->getSource(), $run->getTrigger()],
        );
    }
}
