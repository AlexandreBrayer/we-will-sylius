<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Yack\Steering\Domain\AgedBalance;
use App\Yack\Steering\Domain\CustomerBalance;

final readonly class CustomersDeskReport
{
    public function __construct(private AlertBoard $alerts)
    {
    }

    public function build(): CustomersDesk
    {
        $snapshot = $this->alerts->snapshot();

        return new CustomersDesk(
            $snapshot,
            CustomerBalance::byCustomer($snapshot->receivables, $snapshot->now),
            AgedBalance::of($snapshot->receivables, $snapshot->now),
        );
    }
}
