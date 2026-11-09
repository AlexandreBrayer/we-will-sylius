<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Fixture;

use App\Yack\Payment\Infrastructure\Fixture\PlannedOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PlannedOrder::class)]
final class PlannedOrderTest extends TestCase
{
    public function testTheLastEventIsTheLatestDateThatHappened(): void
    {
        $placedAt = new \DateTimeImmutable('2026-07-01 10:00');
        $shippedAt = new \DateTimeImmutable('2026-07-03 15:00');
        $paidAt = new \DateTimeImmutable('2026-07-12 09:00');

        $order = new PlannedOrder('a@exemple.test', $placedAt, ['X' => 2], 'virement', 'transport', $paidAt, $shippedAt, 'YKT000000001', null, null, null);
        $inProgress = new PlannedOrder('a@exemple.test', $placedAt, ['X' => 2], 'virement', 'transport', null, null, null, null, null, 'Note');

        $this->assertSame($paidAt, $order->lastEventAt());
        $this->assertSame($placedAt, $inProgress->lastEventAt());
        $this->assertSame(['X' => 2], $order->lines);
    }
}
