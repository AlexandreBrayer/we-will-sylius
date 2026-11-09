<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Stored edit lock of a page or a template (D13, App\Entity\Content\CmsEditLock), as read by the port
 * CmsEditLocks: who holds it, since when, his last sign of life.
 *
 * The builder of the holder sends a sign of life every HEARTBEAT seconds; a lock without one for TIMEOUT
 * seconds is free, and so is a lock given back (`heartbeatAt` null). `token` is drawn each time the lock
 * changes hands: an editor that still has the token of a lock held by the same administrator knows that
 * nobody took it in between. `version` grows at each write (compare and set of the port).
 */
final readonly class CmsEditLockState
{
    /** Sign of life of the builder, in seconds. */
    public const int HEARTBEAT = 30;

    /** Without a sign of life for this long (seconds), the lock is free. */
    public const int TIMEOUT = 120;

    public function __construct(
        public int $holderId,
        public CmsRevisionAuthor $holder,
        public string $token,
        public \DateTimeImmutable $since,
        public ?\DateTimeImmutable $heartbeatAt,
        public int $version,
    ) {
    }

    /** Held: given a sign of life less than TIMEOUT seconds ago, and not given back. */
    public function isAlive(\DateTimeImmutable $now): bool
    {
        return null !== $this->heartbeatAt && $now->getTimestamp() - $this->heartbeatAt->getTimestamp() < self::TIMEOUT;
    }

    public function isHeldBy(int $adminId, \DateTimeImmutable $now): bool
    {
        return $this->holderId === $adminId && $this->isAlive($now);
    }

    /** Held by another administrator: $adminId may only read the content. */
    public function blocks(int $adminId, \DateTimeImmutable $now): bool
    {
        return $this->holderId !== $adminId && $this->isAlive($now);
    }

    /** End of the lock when no sign of life comes, null when it is free. */
    public function expiresAt(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        return $this->isAlive($now) && null !== $this->heartbeatAt ? $this->heartbeatAt->modify('+' . self::TIMEOUT . ' seconds') : null;
    }
}
