<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\CmsEditLockAction;
use App\Yack\Content\Domain\CmsEditLocked;
use App\Yack\Content\Domain\CmsEditLocks;
use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsEditLockStatus;
use Psr\Clock\ClockInterface;

/**
 * Edit lock of a page or a template (D13). The lock belongs to an administrator, not to a browser tab:
 * the same administrator in two tabs shares it.
 *  - acquire: free lock (never taken, given back, or silent for CmsEditLockState::TIMEOUT seconds) or
 *    already held by the administrator: he holds it; otherwise nothing changes;
 *  - heartbeat: sign of life, only with the token of the lock (a token that no longer matches means the
 *    lock changed hands in between: the editor that sent it is told, it is not given the lock again);
 *  - takeover: « Prendre la main », the lock goes to the administrator whoever holds it;
 *  - release: gives the lock back, only with its token;
 *  - save(): a save of the content is refused (CmsEditLocked, 409) while another administrator holds the
 *    lock; free or own lock: it goes through and leaves the lock as it is. Without an administrator
 *    (fixtures, commands) a save is never refused;
 *  - blocking(), blockingForWrite(): the lock that keeps an administrator from writing a content, for a
 *    write of several contents that leaves out the locked ones instead of refusing everything (bulk style
 *    replacement). Same rule as save().
 * Every answer is the lock as the administrator sees it after the operation. Writes are compare and set
 * (CmsEditLocks): when two requests race, the loser reads the lock again and answers from it.
 */
final readonly class CmsEditLocking
{
    /** Compare and set attempts before answering with the lock as it is. */
    private const int ATTEMPTS = 5;

    public function __construct(
        private CmsEditLocks $locks,
        private ClockInterface $clock,
    ) {
    }

    public function status(CmsPage|CmsTemplate $target, AdminUser $admin): CmsEditLockStatus
    {
        return CmsEditLockStatus::of($this->locks->find($target), $this->id($admin), $this->now());
    }

    public function apply(CmsEditLockAction $action, CmsPage|CmsTemplate $target, AdminUser $admin, ?string $token = null): CmsEditLockStatus
    {
        $me = $this->id($admin);
        for ($attempt = 1; $attempt <= self::ATTEMPTS; ++$attempt) {
            $now = $this->now();
            $lock = $this->locks->find($target);
            $done = match ($action) {
                CmsEditLockAction::Acquire => $this->acquire($target, $admin, $me, $lock, $now),
                CmsEditLockAction::Heartbeat => $this->heartbeat($target, $admin, $me, $lock, $token, $now),
                CmsEditLockAction::Takeover => $this->takeover($target, $admin, $me, $lock, $now),
                CmsEditLockAction::Release => $this->release($target, $admin, $me, $lock, $token),
            };
            if ($done) {
                break;
            }
        }

        return $this->status($target, $admin);
    }

    /**
     * Runs the save $write unless another administrator holds the lock, in the transaction that holds the
     * lock row (CmsEditLocks::serialized()). A content not saved yet (POST) has no lock.
     *
     * @template T
     *
     * @param callable(): T $write
     *
     * @return T
     *
     * @throws CmsEditLocked
     */
    public function save(CmsPage|CmsTemplate $target, ?AdminUser $author, callable $write): mixed
    {
        if (null === $target->getId() || null === $author) {
            return $write();
        }
        $me = $this->id($author);

        return $this->locks->serialized($target, function (?CmsEditLockState $lock) use ($target, $me, $write): mixed {
            if (null !== $lock && $lock->blocks($me, $this->now())) {
                throw new CmsEditLocked($lock, $target instanceof CmsTemplate);
            }

            return $write();
        });
    }

    /**
     * Lock that keeps $admin from writing the target: held by another administrator, with a sign of life
     * less than CmsEditLockState::TIMEOUT seconds old. Null when the target is free or held by $admin, for
     * a content not saved yet, and without an administrator (fixtures, commands). Read only: what a
     * preview shows.
     */
    public function blocking(CmsPage|CmsTemplate $target, ?AdminUser $admin): ?CmsEditLockState
    {
        if (null === $target->getId() || null === $admin) {
            return null;
        }

        return $this->blocker($this->locks->find($target), $admin);
    }

    /**
     * Same answer as blocking(), read inside the transaction of the caller that writes the target: the
     * lock row stays held until that transaction ends (CmsEditLocks::readForWrite()), so a take over
     * waits for the write and a write never goes through a lock taken in between.
     */
    public function blockingForWrite(CmsPage|CmsTemplate $target, ?AdminUser $admin): ?CmsEditLockState
    {
        if (null === $target->getId() || null === $admin) {
            return null;
        }

        return $this->blocker($this->locks->readForWrite($target), $admin);
    }

    private function blocker(?CmsEditLockState $lock, AdminUser $admin): ?CmsEditLockState
    {
        return null !== $lock && $lock->blocks($this->id($admin), $this->now()) ? $lock : null;
    }

    /** @return bool true when the answer can be read: written, or nothing to write */
    private function acquire(CmsPage|CmsTemplate $target, AdminUser $admin, int $me, ?CmsEditLockState $lock, \DateTimeImmutable $now): bool
    {
        if (null === $lock) {
            return $this->locks->insert($target, $admin, self::token(), $now);
        }
        if ($lock->blocks($me, $now)) {
            return true;
        }
        if ($lock->holderId === $me) {
            // Same administrator (another tab, or his own lock gone silent): nobody took it in between, the token stays.
            return $this->locks->replace($target, $lock, $admin, $lock->token, $lock->isAlive($now) ? $lock->since : $now, $now);
        }

        return $this->locks->replace($target, $lock, $admin, self::token(), $now, $now);
    }

    private function heartbeat(CmsPage|CmsTemplate $target, AdminUser $admin, int $me, ?CmsEditLockState $lock, ?string $token, \DateTimeImmutable $now): bool
    {
        if (null === $lock || null === $token || $lock->holderId !== $me || !hash_equals($lock->token, $token)) {
            return true;
        }

        return $this->locks->replace($target, $lock, $admin, $lock->token, $lock->isAlive($now) ? $lock->since : $now, $now);
    }

    private function takeover(CmsPage|CmsTemplate $target, AdminUser $admin, int $me, ?CmsEditLockState $lock, \DateTimeImmutable $now): bool
    {
        if (null === $lock) {
            return $this->locks->insert($target, $admin, self::token(), $now);
        }
        if ($lock->holderId === $me) {
            return $this->locks->replace($target, $lock, $admin, $lock->token, $lock->isAlive($now) ? $lock->since : $now, $now);
        }

        return $this->locks->replace($target, $lock, $admin, self::token(), $now, $now);
    }

    private function release(CmsPage|CmsTemplate $target, AdminUser $admin, int $me, ?CmsEditLockState $lock, ?string $token): bool
    {
        if (null === $lock || null === $token || null === $lock->heartbeatAt || $lock->holderId !== $me || !hash_equals($lock->token, $token)) {
            return true;
        }

        return $this->locks->replace($target, $lock, $admin, $lock->token, $lock->since, null);
    }

    private function id(AdminUser $admin): int
    {
        return (int) $admin->getId();
    }

    private function now(): \DateTimeImmutable
    {
        // Seconds: what the database keeps (DATETIME), so that a lock read back compares as it was written.
        $now = $this->clock->now();

        return $now->setTimestamp($now->getTimestamp());
    }

    private static function token(): string
    {
        return bin2hex(random_bytes(16));
    }
}
