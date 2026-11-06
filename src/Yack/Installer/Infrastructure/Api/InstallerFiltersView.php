<?php

declare(strict_types=1);

namespace App\Yack\Installer\Infrastructure\Api;

final readonly class InstallerFiltersView
{
    /**
     * @param list<string> $sectors
     * @param list<string> $ranges
     * @param list<array{code: string, label: string}> $activities
     */
    public function __construct(
        public array $sectors,
        public array $ranges,
        public array $activities,
    ) {
    }
}
