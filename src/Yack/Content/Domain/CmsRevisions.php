<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsRevision;
use App\Entity\Content\CmsTemplate;

/**
 * History of the pages and templates (App\Entity\Content\CmsRevision). add() and removeOldest() write
 * nothing by themselves: the flush of the caller writes them with the content saved, in one transaction.
 */
interface CmsRevisions
{
    /** Written by the next flush. */
    public function add(CmsRevision $revision): void;

    public function find(int $id): ?CmsRevision;

    /**
     * Stored revisions of the target (the ones not flushed yet are not counted).
     *
     * @return list<CmsRevisionSummary> newest first
     */
    public function summaries(CmsPage|CmsTemplate $target): array;

    /**
     * Schedules for the next flush the removal of the stored revisions of the target beyond the $keep newest.
     *
     * @return int number of revisions removed
     */
    public function removeOldest(CmsPage|CmsTemplate $target, int $keep): int;
}
