<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

final readonly class StockedVariant
{
    public function __construct(
        public int $productId,
        public string $code,
        public string $name,
        public int $available,
    ) {
    }
}
