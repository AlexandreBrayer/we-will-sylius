<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Application\CmsEditLocking;
use App\Yack\Content\Domain\CmsEditLocked;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Edit lock applied by the write processors of pages and templates (D13): the save runs only when no other
 * administrator holds the lock, inside the transaction that holds the lock row (CmsEditLocking::save());
 * otherwise 409 with the French message of CmsEditLocked. Without an administrator: never refused.
 */
final readonly class CmsEditLockGuard
{
    public function __construct(
        private CmsEditLocking $locking,
        private CmsEditLockTargets $targets,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $write
     *
     * @return T
     *
     * @throws ConflictHttpException
     */
    public function save(CmsPage|CmsTemplate $target, callable $write): mixed
    {
        try {
            return $this->locking->save($target, $this->targets->current(), $write);
        } catch (CmsEditLocked $locked) {
            throw new ConflictHttpException($locked->getMessage(), $locked);
        }
    }
}
