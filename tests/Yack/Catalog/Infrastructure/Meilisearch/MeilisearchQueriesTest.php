<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchQuery;
use App\Yack\Catalog\Domain\SearchUnavailable;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchIndexes;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchQueries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MeilisearchQueries::class)]
final class MeilisearchQueriesTest extends TestCase
{
    public function testSearchBody(): void
    {
        $this->assertSame([
            'q' => 'SRK25',
            'page' => 2,
            'hitsPerPage' => 18,
            'filter' => 'enabled = true',
            'matchingStrategy' => 'all',
            'attributesToRetrieve' => ['code'],
        ], MeilisearchQueries::search(new SearchQuery(' SRK25 ', 18, 2)));
    }

    public function testSearchBodyWithOptionalLastWords(): void
    {
        $this->assertSame('last', MeilisearchQueries::search(new SearchQuery('pac air eau'), MeilisearchQueries::LAST_WORDS_OPTIONAL)['matchingStrategy']);
    }

    public function testSearchBodyOfOneKind(): void
    {
        $this->assertSame('enabled = true AND kind = "spare_part"', MeilisearchQueries::search(new SearchQuery('kaze', kind: ProductKind::SparePart))['filter']);
    }

    public function testSuggestBody(): void
    {
        $body = MeilisearchQueries::suggest(new MeilisearchIndexes('dev_'), 'clim', 6, 3);

        $this->assertSame([
            'indexUid' => 'dev_products',
            'q' => 'clim',
            'limit' => 6,
            'filter' => 'enabled = true',
            'matchingStrategy' => 'last',
            'attributesToRetrieve' => ['code', 'name', 'slug', 'brand', 'image'],
        ], $body['queries'][0]);
        $this->assertSame([
            'indexUid' => 'dev_categories',
            'q' => 'clim',
            'limit' => 3,
            'matchingStrategy' => 'last',
            'attributesToRetrieve' => ['code', 'name', 'slug'],
        ], $body['queries'][1]);
    }

    public function testSearchResult(): void
    {
        $result = MeilisearchQueries::searchResult(['hits' => [['code' => 'SRK25ZSP-W1'], ['code' => 'SRK25ZS-WF'], ['id' => 'sans-code'], 'bruit'], 'totalHits' => 12, 'page' => 1]);

        $this->assertSame(['SRK25ZSP-W1', 'SRK25ZS-WF'], $result->codes);
        $this->assertSame(12, $result->total);
        $this->assertSame(SearchEngine::Meilisearch, $result->engine);
    }

    public function testSearchResultWithAnEstimatedTotal(): void
    {
        $this->assertSame(3, MeilisearchQueries::searchResult(['hits' => [], 'estimatedTotalHits' => 3])->total);
    }

    public function testSuggestions(): void
    {
        $suggestions = MeilisearchQueries::suggestions(['results' => [
            ['hits' => [
                ['code' => 'YK-CLM-P26', 'name' => 'Climatiseur mural Prima 2,6 kW', 'slug' => 'climatiseur-mural-prima', 'brand' => 'Yack', 'image' => 'ab/cd/prima.png'],
                ['code' => 'SRK25ZSP-W1', 'name' => 'Mural Premium 2,5 kW R32', 'slug' => 'mural-premium', 'brand' => ' ', 'image' => null],
                ['code' => 'INCOMPLET', 'name' => 'Sans slug'],
            ]],
            ['hits' => [['code' => 'clim_murale', 'name' => 'Climatiseurs muraux', 'slug' => 'nos-produits/clik-yack/climatisation/muraux'], ['code' => 'sans-nom']]],
        ]]);

        $this->assertSame(SearchEngine::Meilisearch, $suggestions->engine);
        $this->assertCount(2, $suggestions->products);
        $this->assertSame('YK-CLM-P26', $suggestions->products[0]->code);
        $this->assertSame('Yack', $suggestions->products[0]->brand);
        $this->assertSame('ab/cd/prima.png', $suggestions->products[0]->imagePath);
        $this->assertNull($suggestions->products[1]->brand);
        $this->assertNull($suggestions->products[1]->imagePath);
        $this->assertCount(1, $suggestions->categories);
        $this->assertSame('nos-produits/clik-yack/climatisation/muraux', $suggestions->categories[0]->slug);
    }

    /**
     * @param array<mixed> $answer
     */
    #[DataProvider('provideBrokenSearchAnswers')]
    public function testABrokenSearchAnswerIsAnUnavailableEngine(array $answer): void
    {
        $this->expectException(SearchUnavailable::class);

        MeilisearchQueries::searchResult($answer);
    }

    /**
     * @return \Iterator<string, array{array<mixed>}>
     */
    public static function provideBrokenSearchAnswers(): \Iterator
    {
        yield 'no total' => [['hits' => []]];
        yield 'no hits' => [['totalHits' => 0]];
    }

    /**
     * @param array<mixed> $answer
     */
    #[DataProvider('provideBrokenMultiSearchAnswers')]
    public function testABrokenMultiSearchAnswerIsAnUnavailableEngine(array $answer): void
    {
        $this->expectException(SearchUnavailable::class);

        MeilisearchQueries::suggestions($answer);
    }

    /**
     * @return \Iterator<string, array{array<mixed>}>
     */
    public static function provideBrokenMultiSearchAnswers(): \Iterator
    {
        yield 'no results' => [[]];
        yield 'one result' => [['results' => [['hits' => []]]]];
        yield 'no hits' => [['results' => [['hits' => []], ['estimatedTotalHits' => 0]]]];
    }
}
