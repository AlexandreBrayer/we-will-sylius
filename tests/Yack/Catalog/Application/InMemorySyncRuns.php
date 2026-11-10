<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Entity\Catalog\SyncRun;
use App\Entity\Catalog\SyncRunLine;
use App\Yack\Catalog\Domain\ResultPage;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncRunFilter;
use App\Yack\Catalog\Domain\SyncRuns;
use App\Yack\Catalog\Domain\SyncStatus;

/**
 * Hand-written fake of the port: the journal in memory, ids given in the order of the runs added (the
 * lines too, across the runs), as the database would.
 */
final class InMemorySyncRuns implements SyncRuns
{
    /** @var array<int, SyncRun> */
    public array $runs = [];

    /** @var list<int> ids of the runs saved after their addition */
    public array $saved = [];

    private int $lineIds = 0;

    public function add(SyncRun $run): void
    {
        $id = count($this->runs) + 1;
        (new \ReflectionProperty(SyncRun::class, 'id'))->setValue($run, $id);
        foreach ($run->getLines() as $line) {
            (new \ReflectionProperty(SyncRunLine::class, 'id'))->setValue($line, ++$this->lineIds);
        }
        $this->runs[$id] = $run;
    }

    public function save(SyncRun $run): void
    {
        $this->saved[] = (int) $run->getId();
    }

    public function get(int $id): ?SyncRun
    {
        return $this->runs[$id] ?? null;
    }

    public function page(SyncRunFilter $filter, \DateTimeImmutable $now, int $page, int $perPage): ResultPage
    {
        $since = $filter->since($now);
        $runs = array_filter($this->runs, static fn (SyncRun $run): bool => (null === $filter->status || $filter->status === $run->getStatus())
            && (null === $filter->source || $filter->source === $run->getSource())
            && (!$filter->simulationsOnly || $run->isDryRun())
            && (null === $since || $run->getStartedAt() >= $since));
        $runs = self::newestFirst($runs);

        return new ResultPage(array_slice($runs, ($page - 1) * $perPage, $perPage), $page, $perPage, count($runs));
    }

    public function since(\DateTimeImmutable $since): array
    {
        $runs = array_values(array_filter($this->runs, static fn (SyncRun $run): bool => !$run->isDryRun() && $run->getStartedAt() >= $since));
        usort($runs, static fn (SyncRun $a, SyncRun $b): int => $a->getStartedAt() <=> $b->getStartedAt());

        return $runs;
    }

    public function latest(): ?SyncRun
    {
        return self::newestFirst(array_filter($this->runs, static fn (SyncRun $run): bool => !$run->isDryRun()))[0] ?? null;
    }

    public function latestSuccess(): ?SyncRun
    {
        return self::newestFirst(array_filter($this->runs, static fn (SyncRun $run): bool => !$run->isDryRun() && SyncStatus::Success === $run->getStatus()))[0] ?? null;
    }

    public function lines(SyncRun $run, ?SyncOutcome $outcome, int $page, int $perPage): ResultPage
    {
        $lines = array_values(array_filter($run->getLines(), static fn (SyncRunLine $line): bool => null === $outcome || $outcome === $line->getOutcome()));

        return new ResultPage(array_slice($lines, ($page - 1) * $perPage, $perPage), $page, $perPage, count($lines));
    }

    public function rejections(SyncRun $run): array
    {
        return array_values(array_filter($run->getLines(), static fn (SyncRunLine $line): bool => SyncOutcome::Failed === $line->getOutcome()));
    }

    public function replaysOf(SyncRun $run): array
    {
        return array_values(array_filter($this->runs, static fn (SyncRun $replay): bool => null !== $run->getId() && $run->getId() === $replay->getReplayOf()));
    }

    /**
     * @param array<int, SyncRun> $runs
     *
     * @return list<SyncRun>
     */
    private static function newestFirst(array $runs): array
    {
        $runs = array_values($runs);
        usort($runs, static fn (SyncRun $a, SyncRun $b): int => [$b->getStartedAt(), $b->getId()] <=> [$a->getStartedAt(), $a->getId()]);

        return $runs;
    }
}
