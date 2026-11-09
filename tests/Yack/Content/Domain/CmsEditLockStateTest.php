<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\CmsEditLockState;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsEditLockState::class)]
final class CmsEditLockStateTest extends TestCase
{
    public function testALockIsHeldUntilTwoMinutesWithoutSignOfLife(): void
    {
        $lock = self::lock('2026-11-08 14:05:00');

        $this->assertTrue($lock->isAlive(new \DateTimeImmutable('2026-11-08 14:06:59')));
        $this->assertTrue($lock->isHeldBy(1, new \DateTimeImmutable('2026-11-08 14:06:59')));
        $this->assertTrue($lock->blocks(2, new \DateTimeImmutable('2026-11-08 14:06:59')));
        $this->assertFalse($lock->blocks(1, new \DateTimeImmutable('2026-11-08 14:06:59')), 'never for its holder');
        $this->assertSame('2026-11-08 14:07:00', $lock->expiresAt(new \DateTimeImmutable('2026-11-08 14:06:59'))?->format('Y-m-d H:i:s'));

        $later = new \DateTimeImmutable('2026-11-08 14:07:00');
        $this->assertFalse($lock->isAlive($later));
        $this->assertFalse($lock->isHeldBy(1, $later));
        $this->assertFalse($lock->blocks(2, $later));
        $this->assertNull($lock->expiresAt($later));
        $this->assertSame(30, CmsEditLockState::HEARTBEAT);
        $this->assertSame(120, CmsEditLockState::TIMEOUT);
    }

    public function testALockGivenBackIsFree(): void
    {
        $lock = self::lock(null);

        $this->assertFalse($lock->isAlive(new \DateTimeImmutable('2026-11-08 14:05:00')));
        $this->assertFalse($lock->blocks(2, new \DateTimeImmutable('2026-11-08 14:05:00')));
        $this->assertNull($lock->expiresAt(new \DateTimeImmutable('2026-11-08 14:05:00')));
    }

    public function testTheTimeZoneOfTheDatesDoesNotMatter(): void
    {
        $lock = self::lock('2026-11-08 10:05:00', new \DateTimeZone('America/Martinique'));

        $this->assertTrue($lock->isAlive(new \DateTimeImmutable('2026-11-08 14:06:00', new \DateTimeZone('UTC'))));
    }

    private static function lock(?string $heartbeat, ?\DateTimeZone $zone = null): CmsEditLockState
    {
        return new CmsEditLockState(
            1,
            new CmsRevisionAuthor('Sophie', 'Martin', 'Sophie Martin'),
            str_repeat('a', 32),
            new \DateTimeImmutable('2026-11-08 14:00:00'),
            null === $heartbeat ? null : new \DateTimeImmutable($heartbeat, $zone),
            3,
        );
    }
}
