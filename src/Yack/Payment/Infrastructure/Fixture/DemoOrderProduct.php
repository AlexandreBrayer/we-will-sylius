<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Fixture;

/** A variant of the demo catalogue that can be ordered online, with its channel price (cents, excl. tax). */
final readonly class DemoOrderProduct
{
    public function __construct(
        public string $variantCode,
        public int $unitPrice,
        public bool $sparePart,
    ) {
    }
}
