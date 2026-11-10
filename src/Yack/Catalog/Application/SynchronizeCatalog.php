<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncSource;
use App\Yack\Catalog\Domain\SyncTrigger;

/**
 * Command message: mirror a batch of the Yack PIM/ERP API into the shop (command line, back-office,
 * fixtures), or simulate it (`dryRun`: the changes computed, nothing written).
 * Handled synchronously today; routing it to an async transport (RabbitMQ) is configuration only.
 */
final readonly class SynchronizeCatalog
{
    /**
     * @param string|null $operator administrator who asked for it from the back-office
     * @param bool        $recorded false for the seed of the demo catalog only (fixtures)
     */
    public function __construct(
        public string $feed,
        public bool $dryRun = false,
        public SyncTrigger $trigger = SyncTrigger::Console,
        public ?string $operator = null,
        public bool $recorded = true,
    ) {
    }

    public function context(): SyncContext
    {
        return new SyncContext(SyncSource::Api, $this->trigger, $this->operator, $this->dryRun, null, $this->recorded);
    }
}
