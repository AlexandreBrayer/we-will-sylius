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

    /** Criteria typed by a visitor: unknown sectors, ranges or activities are ignored. */
    public static function fromInput(InstallerDirectory $directory, string $sector, string $range, string $activity): self
    {
        return new self(
            sector: in_array($sector, $directory->sectors(), true) ? $sector : null,
            range: in_array($range, $directory->ranges(), true) ? $range : null,
            activity: Activity::tryFrom($activity),
        );
    }
}
