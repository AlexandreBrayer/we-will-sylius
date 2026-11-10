<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

use App\Entity\Catalog\SyncRun;
use App\Entity\Catalog\SyncRunLine;

/** Port: journal of the synchronisations of the catalog (dashboard of the back-office). */
interface SyncRuns
{
    /** Records the run and its lines for good. */
    public function add(SyncRun $run): void;

    /** Saves a change made to a recorded run (address of the alert). */
    public function save(SyncRun $run): void;

    public function get(int $id): ?SyncRun;

    /**
     * Runs matching the filter, newest first.
     *
     * @return ResultPage<SyncRun>
     */
    public function page(SyncRunFilter $filter, \DateTimeImmutable $now, int $page, int $perPage): ResultPage;

    /**
     * Runs that wrote or could have written (simulations left out) since the date, oldest first.
     *
     * @return list<SyncRun>
     */
    public function since(\DateTimeImmutable $since): array;

    /** Newest run that is not a simulation. */
    public function latest(): ?SyncRun;

    /** Newest run in success that is not a simulation. */
    public function latestSuccess(): ?SyncRun;

    /**
     * Lines of a run, in the order of the batch, the outcome given only.
     *
     * @return ResultPage<SyncRunLine>
     */
    public function lines(SyncRun $run, ?SyncOutcome $outcome, int $page, int $perPage): ResultPage;

    /**
     * Rejected lines of a run, in the order of the batch.
     *
     * @return list<SyncRunLine>
     */
    public function rejections(SyncRun $run): array;

    /**
     * Runs that applied again the rejected records of this one, oldest first.
     *
     * @return list<SyncRun>
     */
    public function replaysOf(SyncRun $run): array;
}
