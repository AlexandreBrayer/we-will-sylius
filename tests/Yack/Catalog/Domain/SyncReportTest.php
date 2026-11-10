<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncReport::class)]
final class SyncReportTest extends TestCase
{
    public function testAnEmptyBatchIsASuccessWithEveryCounterAtZero(): void
    {
        $report = new SyncReport('catalog.json');

        $this->assertSame(['created' => 0, 'updated' => 0, 'disabled' => 0, 'unchanged' => 0, 'failed' => 0], $report->toArray());
        $this->assertSame([0, [], [], false, SyncStatus::Success, null, null, false], [$report->total(), $report->changes(), $report->failures(), $report->hasFailures(), $report->status(), $report->error(), $report->runId(), $report->dryRun]);
    }

    public function testEachRecordCountsUnderItsOutcomeAndARejectionMakesThePartialStatus(): void
    {
        $report = new SyncReport('catalog.json', dryRun: true);
        $created = new ProductChange('YK-1', 'Produit 1', SyncOutcome::Created);
        $unchanged = new ProductChange('YK-2', 'Produit 2', SyncOutcome::Unchanged);
        $failure = new RejectedRecord('YK-3', 'Champ « label » absent ou vide.');
        $report->record($created);
        $report->record($unchanged);
        $report->fail($failure);
        $report->recordedAs(12);

        $this->assertSame([1, 1, 1, 3], [$report->count(SyncOutcome::Created), $report->count(SyncOutcome::Unchanged), $report->count(SyncOutcome::Failed), $report->total()]);
        $this->assertSame([$created, $unchanged], $report->changes());
        $this->assertSame([$failure], $report->failures());
        $this->assertTrue($report->hasFailures());
        $this->assertSame(SyncStatus::Partial, $report->status());
        $this->assertSame(12, $report->runId());
        $this->assertTrue($report->dryRun);
    }

    public function testABatchThatCouldNotBeReadIsAFailureWhateverWasCounted(): void
    {
        $report = new SyncReport('catalog.json');
        $report->fail(new RejectedRecord('YK-3', 'Champ « label » absent ou vide.'));
        $report->sourceFailed('API injoignable.');

        $this->assertSame('API injoignable.', $report->error());
        $this->assertSame(SyncStatus::Failure, $report->status());
    }
}
