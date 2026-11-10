<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Domain\ProductSourceHealth;
use App\Yack\Catalog\Domain\ResultPage;
use App\Yack\Catalog\Domain\SourceHealth;
use App\Yack\Catalog\Domain\SyncRunFilter;
use App\Yack\Catalog\Domain\SyncRuns;
use App\Yack\Catalog\Domain\SyncStats;
use Psr\Clock\ClockInterface;

/**
 * Read side of the dashboard of the synchronisations: indicators over 30 days, runs of each day for the
 * chart, health of the Yack API and the journal of the runs.
 */
final readonly class SyncDashboard
{
    /** Period of the indicators and of the chart, in days. */
    public const PERIOD_DAYS = 30;

    public const RUNS_PER_PAGE = 15;

    public function __construct(
        private SyncRuns $runs,
        private ProductSourceHealth $health,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{latest: SyncRun|null, latestSuccess: SyncRun|null, latestSuccessAge: string|null, stats: SyncStats, daily: list<array{day: string, success: int, partial: int, failure: int, written: int}>, health: SourceHealth}
     */
    public function overview(): array
    {
        $now = $this->clock->now();
        $since = $now->modify(sprintf('-%d days', self::PERIOD_DAYS - 1))->setTime(0, 0);
        $stats = SyncStats::of($this->runs->since($since));
        $latestSuccess = $this->runs->latestSuccess();

        return [
            'latest' => $this->runs->latest(),
            'latestSuccess' => $latestSuccess,
            'latestSuccessAge' => null === $latestSuccess ? null : SyncStats::age($latestSuccess->getFinishedAt(), $now),
            'stats' => $stats,
            'daily' => $stats->daily($since, $now),
            'health' => $this->health->check(),
        ];
    }

    /** @return ResultPage<SyncRun> */
    public function runs(SyncRunFilter $filter, int $page): ResultPage
    {
        return $this->runs->page($filter, $this->clock->now(), $page, self::RUNS_PER_PAGE);
    }
}
