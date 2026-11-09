<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\CmsEditLockSituation;
use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsEditLockStatus;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsEditLockStatus::class)]
final class CmsEditLockStatusTest extends TestCase
{
    public function testTheSituationDependsOnWhoAsksAndWhen(): void
    {
        $now = new \DateTimeImmutable('2026-11-08 14:05:30');
        $lock = new CmsEditLockState(1, new CmsRevisionAuthor(null, null, 'sophie@yack.test'), str_repeat('b', 32), new \DateTimeImmutable('2026-11-08 14:00:00'), new \DateTimeImmutable('2026-11-08 14:05:00'), 1);

        $this->assertSame(CmsEditLockSituation::Mine, CmsEditLockStatus::of($lock, 1, $now)->situation);
        $this->assertSame(CmsEditLockSituation::Other, CmsEditLockStatus::of($lock, 2, $now)->situation);
        $this->assertSame(CmsEditLockSituation::Free, CmsEditLockStatus::of($lock, 2, $now->modify('+2 minutes'))->situation);
        $this->assertSame(CmsEditLockSituation::Free, CmsEditLockStatus::of($lock, 1, $now->modify('+2 minutes'))->situation);
        $this->assertSame(CmsEditLockSituation::Free, CmsEditLockStatus::of(null, 1, $now)->situation);
        $this->assertSame($lock, CmsEditLockStatus::of($lock, 2, $now)->lock);
        $this->assertSame($now, CmsEditLockStatus::of($lock, 2, $now)->at);
    }
}
