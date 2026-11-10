<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

use App\Entity\Catalog\SyncRun;

/**
 * Indicators of the dashboard over a period: runs, failures, products written, records read and
 * rejected, and the runs of each day for the chart. Simulations write nothing: they are left out.
 */
final readonly class SyncStats
{
    /**
     * @param list<SyncRun> $runs
     */
    private function __construct(
        private array $runs,
        public int $runCount,
        public int $failures,
        public int $written,
        public int $read,
        public int $rejected,
    ) {
    }

    /** @param list<SyncRun> $runs */
    public static function of(array $runs): self
    {
        $runs = array_values(array_filter($runs, static fn (SyncRun $run): bool => !$run->isDryRun()));

        return new self(
            $runs,
            count($runs),
            count(array_filter($runs, static fn (SyncRun $run): bool => SyncStatus::Failure === $run->getStatus())),
            array_sum(array_map(static fn (SyncRun $run): int => $run->written(), $runs)),
            array_sum(array_map(static fn (SyncRun $run): int => $run->total(), $runs)),
            array_sum(array_map(static fn (SyncRun $run): int => $run->count(SyncOutcome::Failed), $runs)),
        );
    }

    /** Share of the records read that were rejected, in percent with one decimal; null when nothing was read. */
    public function rejectionRate(): ?float
    {
        return 0 === $this->read ? null : round($this->rejected * 100 / $this->read, 1);
    }

    /**
     * Runs of each day from $from to $to (both included, in the time zone of $to), by status, and the
     * products written that day.
     *
     * @return list<array{day: string, success: int, partial: int, failure: int, written: int}>
     */
    public function daily(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $timezone = $to->getTimezone();
        $days = [];
        for ($day = $from->setTimezone($timezone)->setTime(0, 0); $day <= $to; $day = $day->modify('+1 day')) {
            $days[$day->format('Y-m-d')] = ['day' => $day->format('d/m'), 'success' => 0, 'partial' => 0, 'failure' => 0, 'written' => 0];
        }

        foreach ($this->runs as $run) {
            $key = $run->getStartedAt()->setTimezone($timezone)->format('Y-m-d');
            if (!isset($days[$key])) {
                continue;
            }
            ++$days[$key][$run->getStatus()->value];
            $days[$key]['written'] += $run->written();
        }

        return array_values($days);
    }

    /** How long ago, as the dashboard says it: « il y a 5 min », « il y a 3 h », « il y a 2 jours ». */
    public static function age(\DateTimeImmutable $at, \DateTimeImmutable $now): string
    {
        $seconds = $now->getTimestamp() - $at->getTimestamp();

        return match (true) {
            $seconds < 60 => 'à l\'instant',
            $seconds < 3600 => sprintf('il y a %d min', intdiv($seconds, 60)),
            $seconds < 48 * 3600 => sprintf('il y a %d h', intdiv($seconds, 3600)),
            default => sprintf('il y a %d jours', intdiv($seconds, 86400)),
        };
    }
}
