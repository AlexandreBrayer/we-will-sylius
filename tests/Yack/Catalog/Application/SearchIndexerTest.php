<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\SearchIndexer;
use App\Yack\Catalog\Domain\CategoryDocument;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductSearchIndex;
use App\Yack\Catalog\Domain\SearchDocument;
use App\Yack\Catalog\Domain\SearchDocumentSource;
use App\Yack\Catalog\Domain\SearchUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 */
#[CoversClass(SearchIndexer::class)]
final class SearchIndexerTest extends TestCase
{
    private SearchIndexerFakeSource $source;

    private SearchIndexerFakeIndex $index;

    private SearchIndexerRecordingLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->source = new SearchIndexerFakeSource();
        $this->index = new SearchIndexerFakeIndex();
        $this->logger = new SearchIndexerRecordingLogger();
    }

    public function testReindexAllReplacesTheWholeIndex(): void
    {
        $counts = $this->indexer('database')->reindexAll();

        $this->assertSame(['products' => 2, 'categories' => 1], $counts);
        $this->assertSame([['SRK25ZSP-W1', 'FDC224KXZRE2'], ['clim_murale']], $this->index->replaced);
        $this->assertSame([null], $this->source->requestedCodes);
        $this->assertSame('search_index.rebuilt', $this->logger->records[0]['message']);
    }

    public function testReindexAllTellsWhenMeilisearchIsDown(): void
    {
        $this->index->failure = new SearchUnavailable('Meilisearch unreachable');

        $this->expectException(SearchUnavailable::class);

        $this->indexer('meilisearch')->reindexAll();
    }

    public function testRefreshUpdatesTheProductsOnce(): void
    {
        $this->indexer('meilisearch')->refresh(['SRK25ZSP-W1', 'FDC224KXZRE2', 'SRK25ZSP-W1']);

        $this->assertSame([['SRK25ZSP-W1', 'FDC224KXZRE2']], $this->source->requestedCodes);
        $this->assertSame([['SRK25ZSP-W1', 'FDC224KXZRE2']], $this->index->upserted);
        $this->assertSame([['level' => 'info', 'message' => 'search_index.refreshed', 'context' => ['products' => 2]]], $this->logger->records);
    }

    public function testRefreshNeverFails(): void
    {
        $this->index->failure = new SearchUnavailable('Meilisearch answered 503');

        $this->indexer('meilisearch')->refresh(['SRK25ZSP-W1']);

        $this->assertSame('warning', $this->logger->records[0]['level']);
        $this->assertSame(['products' => 1, 'error' => 'Meilisearch answered 503'], $this->logger->records[0]['context']);
    }

    public function testNoRefreshWhenTheShopSearchesTheDatabase(): void
    {
        $this->indexer('database')->refresh(['SRK25ZSP-W1']);

        $this->assertSame([], $this->source->requestedCodes);
        $this->assertSame([], $this->index->upserted);
    }

    public function testNoRefreshWithoutProducts(): void
    {
        $this->indexer('meilisearch')->refresh([]);

        $this->assertSame([], $this->source->requestedCodes);
    }

    private function indexer(string $engine): SearchIndexer
    {
        return new SearchIndexer($this->source, $this->index, $this->logger, $engine);
    }
}

/** Hand-written fake of the port: two products, one category. */
final class SearchIndexerFakeSource implements SearchDocumentSource
{
    /** @var list<list<string>|null> */
    public array $requestedCodes = [];

    public function products(?array $codes = null): array
    {
        $this->requestedCodes[] = $codes;
        $products = [
            new SearchDocument('SRK25ZSP-W1', 'Mural Premium 2,5 kW R32', 'mural-premium-2-5-kw', true, ProductKind::Finished),
            new SearchDocument('FDC224KXZRE2', 'Groupe extérieur DRV KXZ 22,4 kW', 'groupe-exterieur-drv', true, ProductKind::Finished),
        ];

        return null === $codes ? $products : array_values(array_filter($products, static fn (SearchDocument $product): bool => in_array($product->code, $codes, true)));
    }

    public function categories(): array
    {
        return [new CategoryDocument('clim_murale', 'Climatiseurs muraux', 'nos-produits/clik-yack/climatisation/muraux', ProductKind::Finished)];
    }
}

/** Hand-written fake of the port: records the writes or fails. */
final class SearchIndexerFakeIndex implements ProductSearchIndex
{
    /** @var list<list<string>> */
    public array $replaced = [];

    /** @var list<list<string>> */
    public array $upserted = [];

    public ?SearchUnavailable $failure = null;

    public function replaceAll(array $products, array $categories): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }
        $this->replaced = [
            array_map(static fn (SearchDocument $product): string => $product->code, $products),
            array_map(static fn (CategoryDocument $category): string => $category->code, $categories),
        ];
    }

    public function upsert(array $products): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }
        $this->upserted[] = array_map(static fn (SearchDocument $product): string => $product->code, $products);
    }
}

/** Keeps the log records in memory. */
final class SearchIndexerRecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
