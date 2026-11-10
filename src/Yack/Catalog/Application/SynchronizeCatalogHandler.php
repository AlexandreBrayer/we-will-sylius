<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\SyncReport;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reads a batch of the Yack PIM/ERP API and applies it (ApplyProductFeed). When the source does not
 * answer, the failed run is recorded and the administrators alerted: the report returned says why (the
 * bus commits the journal, an exception would roll it back).
 */
#[AsMessageHandler]
final readonly class SynchronizeCatalogHandler
{
    public function __construct(
        private ProductSource $source,
        private ApplyProductFeed $apply,
        private LoggerInterface $yackSyncLogger,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SynchronizeCatalog $command): SyncReport
    {
        $startedAt = $this->clock->now();
        $context = $command->context();
        $this->yackSyncLogger->info('catalog_sync.started', ['feed' => $command->feed, 'dry_run' => $command->dryRun]);

        try {
            $feed = $this->source->fetch($command->feed);
        } catch (ProductSourceUnavailable $exception) {
            $this->yackSyncLogger->critical('catalog_sync.source_unavailable', ['feed' => $command->feed, 'error' => $exception->getMessage()]);

            return $this->apply->recordFailure($context, basename($command->feed), $exception->getMessage(), $startedAt);
        }

        return ($this->apply)($feed, $context, $startedAt);
    }
}
