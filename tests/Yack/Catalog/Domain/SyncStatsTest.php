<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncStats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncStats::class)]
final class SyncStatsTest extends TestCase
{
    public function testTheIndicatorsLeaveTheSimulationsOut(): void
    {
        $stats = SyncStats::of(self::runs());

        $this->assertSame([4, 1, 4, 7, 1], [$stats->runCount, $stats->failures, $stats->written, $stats->read, $stats->rejected]);
        $this->assertSame(14.3, $stats->rejectionRate());
    }

    public function testNothingReadHasNoRejectionRate(): void
    {
        $stats = SyncStats::of([]);

        $this->assertSame([0, null], [$stats->runCount, $stats->rejectionRate()]);
    }

    public function testTheRunsOfEachDayAreCountedInTheTimeZoneOfNow(): void
    {
        $paris = new \DateTimeZone('Europe/Paris');

        $daily = SyncStats::of(self::runs())->daily(new \DateTimeImmutable('2026-11-08 12:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-11-10 06:00', $paris));

        $this->assertSame([
            ['day' => '08/11', 'success' => 1, 'partial' => 0, 'failure' => 0, 'written' => 2],
            ['day' => '09/11', 'success' => 0, 'partial' => 0, 'failure' => 1, 'written' => 0],
            // 23:30 UTC on the 9th is already the 10th in Paris
            ['day' => '10/11', 'success' => 0, 'partial' => 1, 'failure' => 0, 'written' => 1],
        ], $daily);
    }

    #[DataProvider('provideAges')]
    public function testTheAgeAsTheDashboardSaysIt(string $at, string $age): void
    {
        $this->assertSame($age, SyncStats::age(new \DateTimeImmutable($at), new \DateTimeImmutable('2026-11-10 12:00:00')));
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function provideAges(): \Iterator
    {
        yield 'à l\'instant' => ['2026-11-10 11:59:30', 'à l\'instant'];
        yield 'minutes' => ['2026-11-10 11:55:00', 'il y a 5 min'];
        yield 'heures' => ['2026-11-10 09:00:00', 'il y a 3 h'];
        yield 'un jour et demi' => ['2026-11-09 00:00:00', 'il y a 36 h'];
        yield 'jours' => ['2026-11-08 06:00:00', 'il y a 2 jours'];
    }

    /** @return list<SyncRun> */
    private static function runs(): array
    {
        $success = new SyncReport('lot-1');
        $success->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created));
        $success->record(new ProductChange('YK-2', 'Produit 2', SyncOutcome::Updated));
        $success->record(new ProductChange('YK-3', 'Produit 3', SyncOutcome::Unchanged));
        $partial = new SyncReport('lot-2');
        $partial->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Disabled));
        $partial->fail(new RejectedRecord('YK-4', 'Prix absent.'));
        $simulation = new SyncReport('lot-3', dryRun: true);
        $simulation->record(new ProductChange('YK-1', 'Produit 1', SyncOutcome::Updated));
        $old = new SyncReport('lot-0');
        $old->record(new ProductChange('YK-9', 'Produit 9', SyncOutcome::Created));
        $old->record(new ProductChange('YK-8', 'Produit 8', SyncOutcome::Unchanged));

        return [
            self::completed($old, '2026-10-20 06:00 UTC'),
            self::completed($success, '2026-11-08 06:00 UTC'),
            SyncRun::failed(new SyncContext(), 'lot-f', 'API injoignable.', new \DateTimeImmutable('2026-11-09 06:00 UTC'), new \DateTimeImmutable('2026-11-09 06:00:30 UTC')),
            self::completed($partial, '2026-11-09 23:30 UTC'),
            SyncRun::completed($simulation, new SyncContext(dryRun: true), new \DateTimeImmutable('2026-11-09 10:00 UTC'), new \DateTimeImmutable('2026-11-09 10:00 UTC')),
        ];
    }

    private static function completed(SyncReport $report, string $at): SyncRun
    {
        return SyncRun::completed($report, new SyncContext(), new \DateTimeImmutable($at), new \DateTimeImmutable($at));
    }
}
