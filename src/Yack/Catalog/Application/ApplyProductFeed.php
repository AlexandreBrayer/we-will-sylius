<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductCatalog;
use App\Yack\Catalog\Domain\ProductChange;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SyncContext;
use App\Yack\Catalog\Domain\SyncFailureNotifier;
use App\Yack\Catalog\Domain\SyncOutcome;
use App\Yack\Catalog\Domain\SyncReport;
use App\Yack\Catalog\Domain\SyncRuns;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies a batch of Yack records to the shop, whatever its source (API feed, manual import, replay of
 * rejected records): Yack is the master, each product is created, updated, disabled or left unchanged
 * after a field-level comparison with the shop; a bad record is rejected without blocking the rest of
 * the batch. A simulation (dry run) checks and compares without writing. Every run goes to the journal
 * (SyncRuns), and the products written are refreshed in the search index.
 */
final readonly class ApplyProductFeed
{
    public function __construct(
        private ProductCatalog $catalog,
        private SyncRuns $runs,
        private SearchIndexerInterface $searchIndexer,
        private SyncFailureNotifier $notifier,
        private LoggerInterface $yackSyncLogger,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ProductFeed $feed, SyncContext $context, ?\DateTimeImmutable $startedAt = null): SyncReport
    {
        $startedAt ??= $this->clock->now();
        $report = new SyncReport($feed->origin, $context->dryRun);

        foreach ($feed->rejected as $rejected) {
            $report->fail($rejected);
            $this->log($context, 'error', 'catalog_sync.record_rejected', ['sku' => $rejected->reference, 'reason' => $rejected->reason, 'stage' => 'mapping']);
        }

        $written = [];
        foreach ($feed->products as $product) {
            try {
                $change = ProductChange::compare($this->catalog->current($product->code), $product);
                if (SyncOutcome::Unchanged !== $change->outcome) {
                    $context->dryRun ? $this->catalog->check($product) : $this->catalog->apply($product);
                }
            } catch (InvalidSourceProduct $exception) {
                $report->fail(new RejectedRecord($product->code, $exception->getMessage(), $feed->payloads[$product->code] ?? [], $feed->lines[$product->code] ?? null));
                $this->log($context, 'error', 'catalog_sync.record_rejected', ['sku' => $product->code, 'reason' => $exception->getMessage(), 'stage' => 'catalog']);

                continue;
            }

            $report->record($change);
            if ($change->outcome->writes()) {
                $written[] = $product->code;
            }
            $this->log($context, 'info', 'catalog_sync.product_' . $change->outcome->value, [
                'sku' => $product->code,
                'price_cents' => $product->priceInCents,
                'stock' => $product->stock,
                'fields' => array_map(static fn ($field): string => $field->field, $change->fields),
                'dry_run' => $context->dryRun,
            ]);
        }

        if (!$context->dryRun) {
            $this->catalog->commit();
            $this->searchIndexer->refresh($written);
        }

        if ($context->recorded) {
            $run = SyncRun::completed($report, $context, $startedAt, $this->clock->now());
            $this->runs->add($run);
            $report->recordedAs((int) $run->getId());
        }

        $this->log($context, 'info', 'catalog_sync.finished', ['origin' => $feed->origin, 'dry_run' => $context->dryRun, 'run' => $report->runId()] + $report->toArray());

        return $report;
    }

    /**
     * Compares a batch with the shop without writing nor recording anything: the preview of a manual
     * import, before the administrator confirms it.
     */
    public function preview(ProductFeed $feed): SyncReport
    {
        return $this(
            $feed,
            new SyncContext(dryRun: true, recorded: false),
        );
    }

    /**
     * Records a run whose batch could not be read, and alerts the administrators: the report says why,
     * nothing was applied.
     */
    public function recordFailure(SyncContext $context, string $origin, string $error, \DateTimeImmutable $startedAt): SyncReport
    {
        $report = new SyncReport($origin, $context->dryRun);
        $report->sourceFailed($error);
        if (!$context->recorded) {
            return $report;
        }

        $run = SyncRun::failed($context, $origin, $error, $startedAt, $this->clock->now());
        $this->runs->add($run);
        if (!$context->dryRun && null !== ($recipient = $this->notifier->notify($run))) {
            $run->notified($recipient);
            $this->runs->save($run);
        }
        $report->recordedAs((int) $run->getId());

        return $report;
    }

    /**
     * A preview (simulation left out of the journal) writes nothing in the log either.
     *
     * @param array<string, mixed> $data
     */
    private function log(SyncContext $context, string $level, string $message, array $data): void
    {
        if ($context->recorded || !$context->dryRun) {
            $this->yackSyncLogger->log($level, $message, $data);
        }
    }
}
