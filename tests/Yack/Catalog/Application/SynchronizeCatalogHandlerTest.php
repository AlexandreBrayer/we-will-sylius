<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\SearchIndexerInterface;
use App\Yack\Catalog\Application\SynchronizeCatalog;
use App\Yack\Catalog\Application\SynchronizeCatalogHandler;
use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductCatalog;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\RejectedRecord;
use App\Yack\Catalog\Domain\SourceProduct;
use App\Yack\Catalog\Domain\SyncOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 */
#[CoversClass(SynchronizeCatalogHandler::class)]
final class SynchronizeCatalogHandlerTest extends TestCase
{
    private SynchronizeCatalogFakeCatalog $catalog;

    private SynchronizeCatalogFakeIndexer $indexer;

    private SynchronizeCatalogRecordingLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->catalog = new SynchronizeCatalogFakeCatalog();
        $this->indexer = new SynchronizeCatalogFakeIndexer();
        $this->logger = new SynchronizeCatalogRecordingLogger();
    }

    public function testEachRecordIsAppliedThenTheSearchIndexIsRefreshed(): void
    {
        $feed = new ProductFeed('lot-test', [
            self::product('YK-PAE-M08'),
            self::product('YK-BAD-TAXON'),
            self::product('YK-OLD', active: false),
        ], [new RejectedRecord('YK-MALFORMED', 'Missing "label".')]);

        $report = $this->handler($feed)(new SynchronizeCatalog('data/yack/api_feed_update.json'));

        $this->assertSame(1, $report->count(SyncOutcome::Updated));
        $this->assertSame(1, $report->count(SyncOutcome::Disabled));
        $this->assertSame(2, $report->count(SyncOutcome::Failed));
        $this->assertSame(['YK-MALFORMED', 'YK-BAD-TAXON'], array_map(static fn (RejectedRecord $failure): string => $failure->reference, $report->failures()));
        $this->assertTrue($this->catalog->committed);
        // The rejected products are left as they are in the index.
        $this->assertSame([['YK-PAE-M08', 'YK-OLD']], $this->indexer->refreshed);
        $this->assertSame('catalog_sync.finished', end($this->logger->messages));
    }

    public function testAnUnreadableFeedStopsBeforeAnyChange(): void
    {
        $this->expectException(ProductSourceUnavailable::class);

        try {
            $this->handler(null)(new SynchronizeCatalog('absent.json'));
        } finally {
            $this->assertFalse($this->catalog->committed);
            $this->assertSame([], $this->indexer->refreshed);
            $this->assertContains('catalog_sync.source_unavailable', $this->logger->messages);
        }
    }

    private function handler(?ProductFeed $feed): SynchronizeCatalogHandler
    {
        return new SynchronizeCatalogHandler(new SynchronizeCatalogFakeSource($feed), $this->catalog, $this->logger, $this->indexer);
    }

    private static function product(string $code, bool $active = true): SourceProduct
    {
        return new SourceProduct($code, 'Produit ' . $code, $active, ProductKind::Finished, 'pac_air_eau', 649000, 3);
    }
}

/** Hand-written fake of the port: the given feed, or unavailable when there is none. */
final class SynchronizeCatalogFakeSource implements ProductSource
{
    public function __construct(private readonly ?ProductFeed $feed)
    {
    }

    public function fetch(string $feed): ProductFeed
    {
        return $this->feed ?? throw new ProductSourceUnavailable(sprintf('Yack feed "%s" is not readable.', $feed));
    }
}

/** Hand-written fake of the port: a code containing "BAD" is rejected. */
final class SynchronizeCatalogFakeCatalog implements ProductCatalog
{
    public bool $committed = false;

    public function apply(SourceProduct $product): SyncOutcome
    {
        if (str_contains($product->code, 'BAD')) {
            throw new InvalidSourceProduct(sprintf('Product "%s": unknown category/brand taxon "nope".', $product->code));
        }

        return $product->active ? SyncOutcome::Updated : SyncOutcome::Disabled;
    }

    public function commit(): void
    {
        $this->committed = true;
    }
}

/** Hand-written fake of the interface: records the refreshed codes. */
final class SynchronizeCatalogFakeIndexer implements SearchIndexerInterface
{
    /** @var list<list<string>> */
    public array $refreshed = [];

    public function reindexAll(): array
    {
        return ['products' => 0, 'categories' => 0];
    }

    public function refresh(array $codes): void
    {
        $this->refreshed[] = $codes;
    }
}

/** Keeps the log messages in memory. */
final class SynchronizeCatalogRecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }
}
