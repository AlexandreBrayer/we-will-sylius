<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * Tracked variants under the stock threshold: how many, how many with nothing available, and the lowest ones.
 */
final readonly class LowStock
{
    /** @param list<StockedVariant> $lowest */
    public function __construct(
        public int $count,
        public int $outOfStock,
        public array $lowest,
    ) {
    }
}
