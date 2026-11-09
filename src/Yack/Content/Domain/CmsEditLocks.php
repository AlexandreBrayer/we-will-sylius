<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;

/**
 * Edit locks of the builder (D13): at most one per page or template (unique target in the database).
 * Writes are atomic so that two administrators never both hold a lock: insert() fails when another request
 * inserted the lock first, replace() when the lock changed since it was read (compare and set on `version`).
 */
interface CmsEditLocks
{
    /** Lock of the target, null when it was never taken (or the target is not saved yet). */
    public function find(CmsPage|CmsTemplate $target): ?CmsEditLockState;

    /**
     * First lock of the target, held by $holder since $now.
     *
     * @return bool false when the target already has a lock (another request inserted it first)
     */
    public function insert(CmsPage|CmsTemplate $target, AdminUser $holder, string $token, \DateTimeImmutable $now): bool;

    /**
     * Writes holder, token, start and sign of life when the lock is still at version `$seen->version`.
     * `$heartbeatAt` null gives the lock back.
     *
     * @return bool false when the lock changed since $seen was read
     */
    public function replace(CmsPage|CmsTemplate $target, CmsEditLockState $seen, AdminUser $holder, string $token, \DateTimeImmutable $since, ?\DateTimeImmutable $heartbeatAt): bool;

    /**
     * Runs $work in one database transaction that holds the lock row of the target (read for update):
     * a take over waits for the end of a save that read the lock, and a save that comes after a take
     * over reads the new holder. Rolled back when $work throws, the exception goes on.
     *
     * @template T
     *
     * @param callable(?CmsEditLockState): T $work receives the lock as it is in the transaction
     *
     * @return T
     */
    public function serialized(CmsPage|CmsTemplate $target, callable $work): mixed;
}
