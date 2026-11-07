<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductSearchIndex;
use App\Yack\Catalog\Domain\SearchDocumentSource;
use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchUnavailable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Feeds the search index from the shop catalogue. The refresh after a catalogue change runs only
 * when Meilisearch answers the shop (YACK_SEARCH_ENGINE=meilisearch): the database needs no index.
 */
final readonly class SearchIndexer implements SearchIndexerInterface
{
    private SearchEngine $engine;

    public function __construct(
        private SearchDocumentSource $documents,
        private ProductSearchIndex $index,
        private LoggerInterface $yackSearchLogger,
        #[Autowire('%env(YACK_SEARCH_ENGINE)%')]
        string $engine,
    ) {
        $this->engine = SearchEngine::fromConfig($engine);
    }

    public function reindexAll(): array
    {
        $products = $this->documents->products();
        $categories = $this->documents->categories();
        $this->index->replaceAll($products, $categories);

        $counts = ['products' => count($products), 'categories' => count($categories)];
        $this->yackSearchLogger->info('search_index.rebuilt', $counts);

        return $counts;
    }

    public function refresh(array $codes): void
    {
        $codes = array_values(array_unique($codes));
        if (SearchEngine::Meilisearch !== $this->engine || [] === $codes) {
            return;
        }

        try {
            $products = $this->documents->products($codes);
            $this->index->upsert($products);
            $this->yackSearchLogger->info('search_index.refreshed', ['products' => count($products)]);
        } catch (SearchUnavailable $exception) {
            $this->yackSearchLogger->warning('search_index.refresh_failed', ['products' => count($codes), 'error' => $exception->getMessage()]);
        }
    }
}
