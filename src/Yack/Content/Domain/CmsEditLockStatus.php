<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** Edit lock of a target as one administrator sees it at `$at` (answer of every lock operation). */
final readonly class CmsEditLockStatus
{
    public function __construct(
        public CmsEditLockSituation $situation,
        public ?CmsEditLockState $lock,
        public \DateTimeImmutable $at,
    ) {
    }

    public static function of(?CmsEditLockState $lock, int $adminId, \DateTimeImmutable $at): self
    {
        return new self(match (true) {
            null === $lock || !$lock->isAlive($at) => CmsEditLockSituation::Free,
            $lock->holderId === $adminId => CmsEditLockSituation::Mine,
            default => CmsEditLockSituation::Other,
        }, $lock, $at);
    }
}
