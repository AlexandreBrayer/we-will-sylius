<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\SearchUnavailable;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Needs symfony/http-client (vendor): runs with PHPUnit on the Mac, not on the agents' bench.
 *
 * @internal
 */
#[CoversClass(MeilisearchClient::class)]
final class MeilisearchClientTest extends TestCase
{
    public function testTheRequestCarriesTheKeyTheJsonBodyAndTheTimeout(): void
    {
        $seen = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = [$method, $url, $options['normalized_headers']['authorization'][0] ?? null, $options['body'] ?? null, $options['timeout'] ?? null];

            return new MockResponse('{"hits":[],"totalHits":0}');
        });

        $answer = (new MeilisearchClient($http, 'http://meilisearch:7700/', 'cle'))->request('POST', '/indexes/products/search', ['q' => 'pac']);

        $this->assertSame(['hits' => [], 'totalHits' => 0], $answer);
        $this->assertSame(['POST', 'http://meilisearch:7700/indexes/products/search', 'Authorization: Bearer cle', '{"q":"pac"}', MeilisearchClient::QUERY_TIMEOUT], $seen);
    }

    public function testNoKeyNoAuthorization(): void
    {
        $authorization = 'unset';
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$authorization): MockResponse {
            $authorization = $options['normalized_headers']['authorization'] ?? null;

            return new MockResponse('{"status":"available"}');
        });

        (new MeilisearchClient($http, 'http://127.0.0.1:7700', ''))->request('GET', '/health');

        $this->assertNull($authorization);
    }

    public function testAnUnreachableServerIsAnUnavailableEngine(): void
    {
        $client = new MeilisearchClient(new MockHttpClient(new MockResponse('', ['error' => 'Could not resolve host: meilisearch'])), 'http://meilisearch:7700', 'cle');

        $this->expectException(SearchUnavailable::class);
        $this->expectExceptionMessage('Meilisearch unreachable (POST /multi-search): Could not resolve host: meilisearch');

        $client->request('POST', '/multi-search', ['queries' => []]);
    }

    public function testAnErrorStatusIsAnUnavailableEngine(): void
    {
        $client = new MeilisearchClient(new MockHttpClient(new MockResponse('{"message":"Index `products` not found.","code":"index_not_found"}', ['http_code' => 404])), 'http://meilisearch:7700', 'cle');

        $this->expectException(SearchUnavailable::class);
        $this->expectExceptionMessage('Meilisearch answered 404 to POST /indexes/products/search: Index `products` not found.');

        $client->request('POST', '/indexes/products/search', ['q' => 'pac']);
    }

    public function testAnUnreadableAnswerIsAnUnavailableEngine(): void
    {
        $client = new MeilisearchClient(new MockHttpClient(new MockResponse('<html>proxy</html>', ['http_code' => 200])), 'http://meilisearch:7700', 'cle');

        $this->expectException(SearchUnavailable::class);

        $client->request('GET', '/health');
    }

    public function testWaitPollsTheTaskUntilItSucceeds(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"uid":7,"status":"enqueued"}'),
            new MockResponse('{"uid":7,"status":"processing"}'),
            new MockResponse('{"uid":7,"status":"succeeded"}'),
        ]);

        (new MeilisearchClient($http, 'http://meilisearch:7700', 'cle'))->wait(['taskUid' => 7]);

        $this->assertSame(3, $http->getRequestsCount());
    }

    public function testAFailedTaskIsAnUnavailableEngine(): void
    {
        $http = new MockHttpClient(new MockResponse('{"uid":8,"status":"failed","error":{"message":"Invalid synonyms."}}'));

        $this->expectException(SearchUnavailable::class);
        $this->expectExceptionMessage('Meilisearch task 8 failed: Invalid synonyms.');

        (new MeilisearchClient($http, 'http://meilisearch:7700', 'cle'))->wait(['taskUid' => 8]);
    }

    public function testATaskThatLastsTooLongIsAnUnavailableEngine(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"uid":9,"status":"processing"}'));

        $this->expectException(SearchUnavailable::class);
        $this->expectExceptionMessage('Meilisearch task 9 still running');

        (new MeilisearchClient($http, 'http://meilisearch:7700', 'cle'))->wait(['taskUid' => 9], 0.01);
    }

    public function testAWriteWithoutTaskIsAnUnavailableEngine(): void
    {
        $this->expectException(SearchUnavailable::class);

        (new MeilisearchClient(new MockHttpClient(), 'http://meilisearch:7700', 'cle'))->wait(['message' => 'no task']);
    }
}
