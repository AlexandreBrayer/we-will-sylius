<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchQuery;
use App\Yack\Catalog\Domain\SearchUnavailable;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchClient;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchIndexes;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchProductSearch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Needs symfony/http-client (vendor): runs with PHPUnit on the Mac, not on the agents' bench.
 *
 * @internal
 */
#[CoversClass(MeilisearchProductSearch::class)]
final class MeilisearchProductSearchTest extends TestCase
{
    /** @var list<array{string, string, mixed}> */
    private array $requests = [];

    public function testSearch(): void
    {
        $result = $this->search('{"hits":[{"code":"SRK25ZSP-W1"},{"code":"SRK25ZS-WF"}],"totalHits":2,"page":1}')
            ->search(new SearchQuery('SRK25', 9));

        $this->assertSame(['SRK25ZSP-W1', 'SRK25ZS-WF'], $result->codes);
        $this->assertSame(2, $result->total);
        $this->assertSame(SearchEngine::Meilisearch, $result->engine);
        $this->assertSame('POST', $this->requests[0][0]);
        $this->assertSame('http://meilisearch:7700/indexes/yack_products/search', $this->requests[0][1]);
        $this->assertSame(['q' => 'SRK25', 'page' => 1, 'hitsPerPage' => 9, 'filter' => 'enabled = true', 'matchingStrategy' => 'all', 'attributesToRetrieve' => ['code'], 'attributesToSearchOn' => ['code', 'manufacturer_reference']], $this->requests[0][2]);
        $this->assertCount(1, $this->requests);
    }

    public function testAReferenceFoundNowhereInTheReferencesIsSearchedInEveryText(): void
    {
        $this->search('{"hits":[],"totalHits":0}')->search(new SearchQuery('SRK26'));

        $this->assertCount(2, $this->requests);
        $this->assertSame(['code', 'manufacturer_reference'], $this->requests[0][2]['attributesToSearchOn'] ?? null);
        $this->assertFalse(isset($this->requests[1][2]['attributesToSearchOn']));
    }

    public function testTheLastWordsBecomeOptionalWhenNoProductHasThemAll(): void
    {
        $result = $this->search('{"hits":[],"totalHits":0}')->search(new SearchQuery('pompe chaleur xyz'));

        $this->assertSame(0, $result->total);
        $this->assertSame(['all', 'last'], array_map(static fn (array $request): mixed => is_array($request[2]) ? $request[2]['matchingStrategy'] ?? null : null, $this->requests));
    }

    public function testAOneWordQueryIsAskedOnce(): void
    {
        $this->search('{"hits":[],"totalHits":0}')->search(new SearchQuery('gainable'));

        $this->assertCount(1, $this->requests);
    }

    public function testSuggestInOneRoundTrip(): void
    {
        $suggestions = $this->search('{"results":[{"indexUid":"yack_products","hits":[{"code":"FDC224KXZRE2","name":"Groupe extérieur DRV KXZ 22,4 kW","slug":"groupe-exterieur-drv"}]},{"indexUid":"yack_categories","hits":[]}]}')
            ->suggest('drv', 6, 3);

        $this->assertSame('FDC224KXZRE2', $suggestions->products[0]->code);
        $this->assertSame([], $suggestions->categories);
        $this->assertCount(1, $this->requests);
        $this->assertSame('http://meilisearch:7700/multi-search', $this->requests[0][1]);
    }

    public function testSuggestionsOfACategoryListAreFilteredOnBothIndexes(): void
    {
        $this->search('{"results":[{"hits":[]},{"hits":[]}]}')->suggest('compresseur', 6, 3, 'clik_yack_parts');

        $queries = $this->requests[0][2]['queries'] ?? [];
        $this->assertSame(
            ['enabled = true AND category_codes = "clik_yack_parts"', 'ancestor_codes = "clik_yack_parts"'],
            array_map(static fn (array $query): mixed => $query['filter'] ?? null, $queries),
        );
    }

    public function testNothingIsAskedForABlankText(): void
    {
        $search = $this->search('{}');

        $this->assertSame(0, $search->search(new SearchQuery(' '))->total);
        $this->assertSame([], $search->suggest(' ', 6, 3)->products);
        $this->assertSame([], $this->requests);
    }

    public function testAMissingIndexIsAnUnavailableEngine(): void
    {
        $this->expectException(SearchUnavailable::class);

        $this->search('{"message":"Index `yack_products` not found.","code":"index_not_found"}', 404)->search(new SearchQuery('pac'));
    }

    private function search(string $answer, int $status = 200): MeilisearchProductSearch
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($answer, $status): MockResponse {
            $this->requests[] = [$method, $url, json_decode(is_string($options['body'] ?? null) ? $options['body'] : 'null', true)];

            return new MockResponse($answer, ['http_code' => $status]);
        });

        return new MeilisearchProductSearch(new MeilisearchClient($http, 'http://meilisearch:7700', 'cle'), new MeilisearchIndexes('yack_'));
    }
}
