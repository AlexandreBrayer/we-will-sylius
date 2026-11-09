<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Yack\Steering\Domain\AgedBalance;
use App\Yack\Steering\Domain\CustomerBalance;

/**
 * « Clients et encours » page: unpaid échéances by customer, balance âgée, PRO requests to validate (in the snapshot).
 */
final readonly class CustomersDesk
{
    /** @param list<CustomerBalance> $balances */
    public function __construct(
        public SteeringSnapshot $snapshot,
        public array $balances,
        public AgedBalance $agedBalance,
    ) {
    }

    public function lateCustomers(): int
    {
        return \count(array_filter($this->balances, static fn (CustomerBalance $b): bool => $b->isLate()));
    }
}
