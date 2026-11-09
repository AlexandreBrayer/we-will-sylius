<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Infrastructure\Anthropic;

use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Infrastructure\Anthropic\AiUsageGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(AiUsageGuard::class)]
final class AiUsageGuardTest extends TestCase
{
    public function testCapIsPerDay(): void
    {
        $cache = new ArrayAdapter();
        $day = new \DateTimeImmutable('2026-11-10');
        $guard = new AiUsageGuard($cache, '2', static fn (): \DateTimeImmutable => $day);
        $guard->consume();
        $guard->consume();

        try {
            $guard->consume();
            $this->fail('Le troisième appel du jour aurait dû être refusé.');
        } catch (AiUnavailable $e) {
            $this->assertStringContainsString('plafond quotidien', $e->getMessage());
        }

        $nextDay = new AiUsageGuard($cache, '2', static fn (): \DateTimeImmutable => new \DateTimeImmutable('2026-11-11'));
        $nextDay->consume();
        $this->assertTrue($cache->getItem('yack_ai_calls_20261111')->isHit());
    }

    public function testEmptyCapMeansDefault(): void
    {
        $cache = new ArrayAdapter();
        $guard = new AiUsageGuard($cache, '');
        for ($i = 0; $i < AiUsageGuard::DEFAULT_CAP; ++$i) {
            $guard->consume();
        }

        $this->expectException(AiUnavailable::class);
        $guard->consume();
    }

    public function testZeroCapBlocksEverything(): void
    {
        $this->expectException(AiUnavailable::class);
        (new AiUsageGuard(new ArrayAdapter(), '0'))->consume();
    }
}
