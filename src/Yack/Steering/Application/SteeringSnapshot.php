<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Entity\Account\OrderAccessRequest;
use App\Yack\AfterSales\Application\WarrantyEnding;
use App\Yack\Steering\Domain\Alerts;
use App\Yack\Steering\Domain\LowStock;
use App\Yack\Steering\Domain\OrderToShip;
use App\Yack\Steering\Domain\Receivable;

/**
 * What the alerts are computed from, read once per request and shared by the menu badges and the page.
 */
final readonly class SteeringSnapshot
{
    /**
     * @param list<Receivable>         $receivables
     * @param list<OrderToShip>        $ordersToShip
     * @param list<OrderAccessRequest> $pendingRequests
     * @param list<WarrantyEnding>     $warrantiesWithin30Days
     */
    public function __construct(
        public \DateTimeImmutable $now,
        public int $stockThreshold,
        public array $receivables,
        public array $ordersToShip,
        public LowStock $lowStock,
        public array $pendingRequests,
        public array $warrantiesWithin30Days,
        public Alerts $alerts,
    ) {
    }

    /** @return list<Receivable> */
    public function overdueReceivables(): array
    {
        return array_values(array_filter($this->receivables, fn (Receivable $r): bool => $r->isLateAt($this->now)));
    }

    /** @return list<OrderToShip> */
    public function lateShipments(): array
    {
        return array_values(array_filter($this->ordersToShip, fn (OrderToShip $o): bool => $o->isLateAt($this->now)));
    }

    public function receivablesTotal(): int
    {
        return array_sum(array_map(static fn (Receivable $r): int => $r->amount, $this->receivables));
    }

    public function overdueTotal(): int
    {
        return array_sum(array_map(static fn (Receivable $r): int => $r->amount, $this->overdueReceivables()));
    }
}
