<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Application\SyncDashboard;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\ProductSourceHealth;
use App\Yack\Catalog\Domain\SourceHealth;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncRunFilter;
use App\Yack\Catalog\Domain\SyncStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(SyncDashboard::class)]
#[CoversClass(SourceHealth::class)]
final class SyncDashboardTest extends TestCase
{
    private InMemorySyncRuns $runs;

    private SyncDashboard $dashboard;

    private SourceHealth $health;

    #[\Override]
    protected function setUp(): void
    {
        $this->runs = new InMemorySyncRuns();
        $this->health = new SourceHealth(true, 'Flux catalog.json joignable : 12 enregistrements disponibles.');
        $source = $this->createStub(ProductSourceHealth::class);
        $source->method('check')->willReturn($this->health);
        $this->dashboard = new SyncDashboard($this->runs, $source, new MockClock('2026-11-10 12:00:00', 'Europe/Paris'));
    }

    public function testTheOverviewCoversThirtyDaysUpToToday(): void
    {
        $this->add('2026-10-11 23:00', 2);
        $success = $this->add('2026-10-12 08:00', 3);
        $failure = SyncRun::failed(new SyncContext(), 'lot', 'API injoignable.', new \DateTimeImmutable('2026-11-10 09:00'), new \DateTimeImmutable('2026-11-10 09:00'));
        $this->runs->add($failure);

        $overview = $this->dashboard->overview();

        $this->assertSame($failure, $overview['latest']);
        $this->assertSame($success, $overview['latestSuccess']);
        $this->assertSame('il y a 29 jours', $overview['latestSuccessAge']);
        $this->assertSame([2, 1, 3], [$overview['stats']->runCount, $overview['stats']->failures, $overview['stats']->written]);
        $this->assertCount(SyncDashboard::PERIOD_DAYS, $overview['daily']);
        $this->assertSame(['day' => '12/10', 'success' => 1, 'partial' => 0, 'failure' => 0, 'written' => 3], $overview['daily'][0]);
        $this->assertSame(['day' => '10/11', 'success' => 0, 'partial' => 0, 'failure' => 1, 'written' => 0], $overview['daily'][29]);
        $this->assertSame($this->health, $overview['health']);
        $this->assertSame([true, false], [$overview['health']->available, $overview['health']->simulatedOutage]);
    }

    public function testAnEmptyJournalHasNoLatestRun(): void
    {
        $overview = $this->dashboard->overview();

        $this->assertSame([null, null, null, 0], [$overview['latest'], $overview['latestSuccess'], $overview['latestSuccessAge'], $overview['stats']->runCount]);
    }

    public function testTheJournalIsListedByPageOfFifteenRunsWithTheFilter(): void
    {
        for ($day = 1; $day <= 17; ++$day) {
            $this->add(sprintf('2026-11-%02d 06:00', $day), 1);
        }

        $page = $this->dashboard->runs(new SyncRunFilter(SyncStatus::Success), 2);

        $this->assertSame([2, 15, 17, 2], [$page->page, $page->perPage, $page->total, count($page->items)]);
        $this->assertSame(0, $this->dashboard->runs(new SyncRunFilter(SyncStatus::Failure), 1)->total);
    }

    private function add(string $at, int $created): SyncRun
    {
        $report = new SyncReport('lot');
        for ($i = 0; $i < $created; ++$i) {
            $report->record(new ProductChange('YK-' . $i, 'Produit', SyncOutcome::Created));
        }
        $run = SyncRun::completed($report, new SyncContext(), new \DateTimeImmutable($at, new \DateTimeZone('Europe/Paris')), new \DateTimeImmutable($at, new \DateTimeZone('Europe/Paris')));
        $this->runs->add($run);

        return $run;
    }
}
