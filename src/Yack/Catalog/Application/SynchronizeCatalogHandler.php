<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductCatalog;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncReport;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Yack is the master: each record is created, updated or disabled in the shop.
 * A bad record is logged and skipped, it never blocks the rest of the batch.
 */
#[AsMessageHandler]
final readonly class SynchronizeCatalogHandler
{
    public function __construct(
        private ProductSource $source,
        private ProductCatalog $catalog,
        private LoggerInterface $yackSyncLogger,
    ) {
    }

    public function __invoke(SynchronizeCatalog $command): SyncReport
    {
        $this->yackSyncLogger->info('catalog_sync.started', ['feed' => $command->feed]);

        try {
            $feed = $this->source->fetch($command->feed);
        } catch (ProductSourceUnavailable $exception) {
            $this->yackSyncLogger->critical('catalog_sync.source_unavailable', ['feed' => $command->feed, 'error' => $exception->getMessage()]);

            throw $exception;
        }

        $report = new SyncReport($feed->origin);

        foreach ($feed->rejected as $rejected) {
            $report->fail($rejected);
            $this->yackSyncLogger->error('catalog_sync.record_rejected', ['sku' => $rejected->reference, 'reason' => $rejected->reason, 'stage' => 'mapping']);
        }

        foreach ($feed->products as $product) {
            try {
                $outcome = $this->catalog->apply($product);
            } catch (InvalidSourceProduct $exception) {
                $report->fail(new RejectedRecord($product->code, $exception->getMessage()));
                $this->yackSyncLogger->error('catalog_sync.record_rejected', ['sku' => $product->code, 'reason' => $exception->getMessage(), 'stage' => 'catalog']);

                continue;
            }

            $report->record($outcome);
            $this->yackSyncLogger->info('catalog_sync.product_' . $outcome->value, [
                'sku' => $product->code,
                'price_cents' => $product->priceInCents,
                'stock' => $product->stock,
            ]);
        }

        $this->catalog->commit();

        $this->yackSyncLogger->info('catalog_sync.finished', ['origin' => $feed->origin] + $report->toArray());

        return $report;
    }
}
