<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\CmsEditLocks;
use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsRevisionAuthor;

/**
 * CmsEditLocks in memory, with the guarantees of the port (unique target, compare and set on `version`).
 * `$beforeNextWrite` runs once just before the next insert or replace: another request that writes first.
 * `$beforeSerializedRead` runs once inside serialized(), before the lock is read: a take over committed
 * just before the save takes the row. `$transactions` records the outcome of each serialized() call.
 */
final class InMemoryCmsEditLocks implements CmsEditLocks
{
    /** @var array<string, CmsEditLockState> */
    public array $locks = [];

    /** @var (\Closure(): mixed)|null */
    public ?\Closure $beforeNextWrite = null;

    /** @var (\Closure(): mixed)|null */
    public ?\Closure $beforeSerializedRead = null;

    /** @var list<'commit'|'rollback'> */
    public array $transactions = [];

    public int $writes = 0;

    public function find(CmsPage|CmsTemplate $target): ?CmsEditLockState
    {
        return $this->locks[self::key($target)] ?? null;
    }

    public function insert(CmsPage|CmsTemplate $target, AdminUser $holder, string $token, \DateTimeImmutable $now): bool
    {
        $this->concurrentWrite();
        if (isset($this->locks[self::key($target)])) {
            return false;
        }
        $this->locks[self::key($target)] = new CmsEditLockState((int) $holder->getId(), CmsRevisionAuthor::of($holder), $token, $now, $now, 1);
        ++$this->writes;

        return true;
    }

    public function replace(CmsPage|CmsTemplate $target, CmsEditLockState $seen, AdminUser $holder, string $token, \DateTimeImmutable $since, ?\DateTimeImmutable $heartbeatAt): bool
    {
        $this->concurrentWrite();
        $current = $this->locks[self::key($target)] ?? null;
        if (null === $current || $current->version !== $seen->version) {
            return false;
        }
        $this->locks[self::key($target)] = new CmsEditLockState((int) $holder->getId(), CmsRevisionAuthor::of($holder), $token, $since, $heartbeatAt, $current->version + 1);
        ++$this->writes;

        return true;
    }

    public function serialized(CmsPage|CmsTemplate $target, callable $work): mixed
    {
        $before = $this->beforeSerializedRead;
        $this->beforeSerializedRead = null;
        if (null !== $before) {
            $before();
        }

        try {
            $result = $work($this->find($target));
        } catch (\Throwable $exception) {
            $this->transactions[] = 'rollback';

            throw $exception;
        }
        $this->transactions[] = 'commit';

        return $result;
    }

    /** Lock written as it is, outside the rules (a lock found in the database). */
    public function put(CmsPage|CmsTemplate $target, AdminUser $holder, string $token, \DateTimeImmutable $since, ?\DateTimeImmutable $heartbeatAt): void
    {
        $version = isset($this->locks[self::key($target)]) ? $this->locks[self::key($target)]->version : 0;
        $this->locks[self::key($target)] = new CmsEditLockState((int) $holder->getId(), CmsRevisionAuthor::of($holder), $token, $since, $heartbeatAt, $version + 1);
    }

    private function concurrentWrite(): void
    {
        $before = $this->beforeNextWrite;
        $this->beforeNextWrite = null;
        if (null !== $before) {
            $before();
        }
    }

    private static function key(CmsPage|CmsTemplate $target): string
    {
        return ($target instanceof CmsPage ? 'page:' : 'template:') . $target->getId();
    }
}
