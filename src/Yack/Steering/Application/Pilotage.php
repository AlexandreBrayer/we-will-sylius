<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Yack\Steering\Domain\Variation;

/**
 * « Pilotage » page: the five figures, CA by range, how customers pay, the alerts (in the snapshot).
 *
 * @phpstan-type RangeRow array{name: string, amount: int, share: int}
 * @phpstan-type MethodRow array{code: string, name: string, amount: int, share: int, orders: int, escompte: int, rate: float|null}
 */
final readonly class Pilotage
{
    /**
     * @param list<RangeRow>  $ranges  CA by range, the highest first (share: bar width relative to the highest)
     * @param list<MethodRow> $methods payment methods, the highest CA first (share: part of the CA, in percent)
     */
    public function __construct(
        public SteeringSnapshot $snapshot,
        public int $revenue,
        public int $previousRevenue,
        public ?Variation $revenueVariation,
        public int $orders,
        public int $previousOrders,
        public int $averageBasket,
        public ?Variation $averageBasketVariation,
        public array $ranges,
        public array $methods,
        public int $escompte,
    ) {
    }
}
