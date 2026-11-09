<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * A spare part ordered over a period: quantity, number of orders, stock available now.
 */
final readonly class PartDemand
{
    public function __construct(
        public int $productId,
        public string $code,
        public string $name,
        public int $quantity,
        public int $orders,
        public bool $tracked,
        public int $available,
    ) {
    }

    public function stockStatus(int $threshold): StockStatus
    {
        return StockStatus::of($this->tracked, $this->available, $threshold);
    }
}
