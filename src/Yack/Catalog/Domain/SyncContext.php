<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Circumstances of a synchronisation: where the records come from, who started it, whether it writes
 * (a simulation computes the changes without writing) and whether it goes to the journal of the runs.
 */
final readonly class SyncContext
{
    /**
     * @param string|null $operator administrator who started it, null from the command line
     * @param int|null    $replayOf run whose rejected records are applied again
     * @param bool        $recorded false for the seed of the demo catalog only (fixtures)
     */
    public function __construct(
        public SyncSource $source = SyncSource::Api,
        public SyncTrigger $trigger = SyncTrigger::Console,
        public ?string $operator = null,
        public bool $dryRun = false,
        public ?int $replayOf = null,
        public bool $recorded = true,
    ) {
    }
}
