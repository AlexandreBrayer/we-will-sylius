<?php

declare(strict_types=1);

namespace App\Tests\Yack\Steering\Domain;

use App\Yack\Steering\Domain\AgedBalance;
use App\Yack\Steering\Domain\CustomerBalance;
use App\Yack\Steering\Domain\Receivable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AgedBalance::class)]
#[CoversClass(CustomerBalance::class)]
final class AgedBalanceTest extends TestCase
{
    public function testTheSlicesOfTheOverdue(): void
    {
        $now = new \DateTimeImmutable('2026-11-09 15:00');
        $balance = AgedBalance::of([
            self::due('2026-11-09', 100),   // échéance aujourd'hui : pas en retard
            self::due('2026-11-20', 200),   // à échoir
            self::due('2026-11-08', 300),   // 1 jour
            self::due('2026-10-10', 400),   // 30 jours
            self::due('2026-10-09', 500),   // 31 jours
            self::due('2026-09-10', 600),   // 60 jours
            self::due('2026-09-09', 700),   // 61 jours
        ], $now);

        $this->assertSame([300, 700, 1_100, 700], [$balance->notDue, $balance->upTo30, $balance->from31To60, $balance->over60]);
        $this->assertSame(2_500, $balance->overdue());
        $this->assertSame(2_800, $balance->total());
        $this->assertSame([28, 44, 28], array_column($balance->rows(), 'share'));
        $this->assertSame(['1 à 30 jours', '31 à 60 jours', 'Plus de 60 jours'], array_column($balance->rows(), 'label'));
    }

    public function testNothingOverdueGivesEmptyBars(): void
    {
        $balance = AgedBalance::of([self::due('2026-12-01', 900)], new \DateTimeImmutable('2026-11-09'));

        $this->assertSame([0, 0, 0], array_column($balance->rows(), 'share'));
        $this->assertSame(900, $balance->notDue);
    }

    public function testOneLineByCustomerTheLatestFirst(): void
    {
        $now = new \DateTimeImmutable('2026-11-09');
        $balances = CustomerBalance::byCustomer([
            self::due('2026-11-30', 1_000, 2),
            self::due('2026-10-01', 400, 1),
            self::due('2026-11-01', 600, 1),
        ], $now);

        $this->assertSame([1, 2], array_map(static fn (CustomerBalance $b): int => $b->customerId, $balances));
        $this->assertSame([1_000, 1_000, '2026-10-01', 39], [$balances[0]->total, $balances[0]->overdue, $balances[0]->oldestDueAt->format('Y-m-d'), $balances[0]->daysLate]);
        $this->assertFalse($balances[1]->isLate());
    }

    private static function due(string $dueAt, int $amount, int $customer = 1): Receivable
    {
        $due = new \DateTimeImmutable($dueAt);

        return new Receivable(1, '000000001', $customer, 'Client '.$customer, $amount, $due->modify('-30 days'), $due);
    }
}
