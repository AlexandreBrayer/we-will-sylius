<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** State of the Yack PIM/ERP source when the back-office asks for it. */
final readonly class SourceHealth
{
    public function __construct(
        public bool $available,
        public string $message,
        public bool $simulatedOutage = false,
    ) {
    }
}
