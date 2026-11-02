<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

final readonly class RejectedRecord
{
    public function __construct(
        public string $reference,
        public string $reason,
    ) {
    }
}
