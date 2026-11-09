<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Fixture;

/**
 * A customer as seen by the demo order planner: a company (B2B quantities, business hours) or an
 * individual, a guaranteed history (minimum number of orders) and a share of the other orders
 * (weight 0: none).
 */
final readonly class DemoOrderCustomer
{
    public function __construct(
        public string $email,
        public \DateTimeImmutable $registeredAt,
        public bool $business,
        public int $minimumOrders,
        public int $weight,
    ) {
    }
}
