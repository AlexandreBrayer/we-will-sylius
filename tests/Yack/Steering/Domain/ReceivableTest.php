<?php

declare(strict_types=1);

namespace App\Tests\Yack\Steering\Domain;

use App\Yack\Steering\Domain\Receivable;
use App\Yack\Steering\Domain\UnpaidOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Receivable::class)]
final class ReceivableTest extends TestCase
{
    public function testTheEcheanceIsTheOrderDayPlusTheDaysOfTheMethod(): void
    {
        $receivable = Receivable::fromOrder(self::order('2026-10-14 16:25'), 30);

        $this->assertSame('2026-11-13 00:00', $receivable->dueAt->format('Y-m-d H:i'));
    }

    public function testLateFromTheDayAfterTheEcheance(): void
    {
        $receivable = Receivable::fromOrder(self::order('2026-10-14 16:25'), 30);

        $this->assertSame(0, $receivable->daysLateAt(new \DateTimeImmutable('2026-11-12 18:00')));
        $this->assertSame(0, $receivable->daysLateAt(new \DateTimeImmutable('2026-11-13 23:59')));
        $this->assertFalse($receivable->isLateAt(new \DateTimeImmutable('2026-11-13 23:59')));
        $this->assertSame(1, $receivable->daysLateAt(new \DateTimeImmutable('2026-11-14 00:01')));
        $this->assertSame(38, $receivable->daysLateAt(new \DateTimeImmutable('2026-12-21 09:00')));
    }

    public function testATransferHasAPositiveDelay(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Receivable::fromOrder(self::order('2026-10-14 16:25'), 0);
    }

    public static function order(string $at, int $total = 100_00, int $customer = 1): UnpaidOrder
    {
        return new UnpaidOrder(7, '000000007', $customer, 'Client '.$customer, $total, new \DateTimeImmutable($at), 'virement_echeance');
    }
}
