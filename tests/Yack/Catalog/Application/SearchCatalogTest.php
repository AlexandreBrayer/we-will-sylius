<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\SearchCatalog;
use App\Yack\Catalog\Domain\CategorySuggestion;
use App\Yack\Catalog\Domain\ProductSearch;
use App\Yack\Catalog\Domain\ProductSuggestion;
use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchQuery;
use App\Yack\Catalog\Domain\SearchResult;
use App\Yack\Catalog\Domain\SearchUnavailable;
use App\Yack\Catalog\Domain\Suggestions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 */
#[CoversClass(SearchCatalog::class)]
final class SearchCatalogTest extends TestCase
{
    private SearchCatalogFakeSearch $meilisearch;

    private SearchCatalogFakeSearch $database;

    private SearchCatalogRecordingLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->meilisearch = new SearchCatalogFakeSearch(SearchEngine::Meilisearch);
        $this->database = new SearchCatalogFakeSearch(SearchEngine::Database);
        $this->logger = new SearchCatalogRecordingLogger();
    }

    public function testMeilisearchAnswersWhenConfigured(): void
    {
        $result = $this->catalog('meilisearch')->search(new SearchQuery('SRK25'));

        $this->assertSame(SearchEngine::Meilisearch, $result->engine);
        $this->assertSame(['SRK25'], $this->meilisearch->searched);
        $this->assertSame([], $this->database->searched);
        $this->assertSame([], $this->logger->records);
    }

    public function testTheDatabaseAnswersWhenMeilisearchIsDown(): void
    {
        $this->meilisearch->failure = new SearchUnavailable('Meilisearch unreachable (POST /indexes/products/search): Connection refused');

        $result = $this->catalog('meilisearch')->search(new SearchQuery('pac air eau'));

        $this->assertSame(SearchEngine::Database, $result->engine);
        $this->assertSame(['pac air eau'], $this->database->searched);
        $this->assertSame([[
            'level' => 'warning',
            'message' => 'search.fallback_to_database',
            'context' => ['operation' => 'search', 'query' => 'pac air eau', 'error' => 'Meilisearch unreachable (POST /indexes/products/search): Connection refused'],
        ]], $this->logger->records);
    }

    public function testTheDatabaseAnswersWhenConfigured(): void
    {
        $catalog = $this->catalog('database');

        $this->assertSame(SearchEngine::Database, $catalog->engine);
        $this->assertSame(SearchEngine::Database, $catalog->search(new SearchQuery('clim'))->engine);
        $this->assertSame([], $this->meilisearch->searched);
    }

    public function testABlankSearchAsksNoEngine(): void
    {
        $result = $this->catalog('meilisearch')->search(new SearchQuery('   '));

        $this->assertSame([], $result->codes);
        $this->assertSame(SearchEngine::Meilisearch, $result->engine);
        $this->assertSame([], $this->meilisearch->searched);
    }

    public function testSuggestionsWithTheDefaultLimits(): void
    {
        $suggestions = $this->catalog('meilisearch')->suggest('  clim   murale ');

        $this->assertSame(SearchEngine::Meilisearch, $suggestions->engine);
        $this->assertSame([['clim murale', SearchCatalog::SUGGESTED_PRODUCTS, SearchCatalog::SUGGESTED_CATEGORIES]], $this->meilisearch->suggested);
    }

    public function testSuggestionLimitsAreBroughtBackInRange(): void
    {
        $this->catalog('database')->suggest('pac', 500, -1);

        $this->assertSame([['pac', SearchCatalog::MAX_SUGGESTIONS, 0]], $this->database->suggested);
    }

    public function testSuggestionsFallBackOnTheDatabase(): void
    {
        $this->meilisearch->failure = new SearchUnavailable('Meilisearch answered 404 to POST /multi-search: Index `categories` not found.');

        $suggestions = $this->catalog('meilisearch')->suggest('drv');

        $this->assertSame(SearchEngine::Database, $suggestions->engine);
        $this->assertSame('suggest', $this->logger->records[0]['context']['operation'] ?? null);
    }

    public function testSuggestionsStartAtTwoCharacters(): void
    {
        $suggestions = $this->catalog('meilisearch')->suggest(' s ');

        $this->assertSame([], $suggestions->products);
        $this->assertSame([], $this->meilisearch->suggested);
    }

    private function catalog(string $engine): SearchCatalog
    {
        return new SearchCatalog($this->meilisearch, $this->database, $this->logger, $engine);
    }
}

/** Hand-written fake of the port: records the calls, answers as its engine or fails. */
final class SearchCatalogFakeSearch implements ProductSearch
{
    /** @var list<string> */
    public array $searched = [];

    /** @var list<array{string, int, int}> */
    public array $suggested = [];

    public ?SearchUnavailable $failure = null;

    public function __construct(private readonly SearchEngine $engine)
    {
    }

    public function search(SearchQuery $query): SearchResult
    {
        $this->searched[] = $query->text;
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return new SearchResult([$query->text], 1, $this->engine);
    }

    public function suggest(string $text, int $productLimit, int $categoryLimit): Suggestions
    {
        $this->suggested[] = [$text, $productLimit, $categoryLimit];
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return new Suggestions([new ProductSuggestion('P1', 'Produit', 'produit')], [new CategorySuggestion('C1', 'Catégorie', 'categorie')], $this->engine);
    }
}

/** Keeps the log records in memory. */
final class SearchCatalogRecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
