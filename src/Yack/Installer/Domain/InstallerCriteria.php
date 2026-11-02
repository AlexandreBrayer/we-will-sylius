<?php

declare(strict_types=1);

namespace App\Yack\Installer\Domain;

final readonly class InstallerCriteria
{
    public function __construct(
        public ?string $sector = null,
        public ?string $range = null,
        public ?Activity $activity = null,
    ) {
    }
}
