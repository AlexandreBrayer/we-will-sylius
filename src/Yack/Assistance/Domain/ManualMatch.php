<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/** Un document retenu pour une référence, avec son niveau et sa raison. */
final readonly class ManualMatch
{
    public MatchLevel $level;

    public function __construct(
        public Manual $manual,
        public MatchReason $reason,
    ) {
        $this->level = $reason->level();
    }
}
