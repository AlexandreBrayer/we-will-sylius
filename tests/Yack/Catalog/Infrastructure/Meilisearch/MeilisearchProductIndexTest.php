<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\CategoryDocument;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SearchDocument;
use App\Yack\Catalog\Domain\SearchUnavailable;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchClient;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchIndexes;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchProductIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Needs symfony/http-client (vendor): runs with PHPUnit on the Mac, not on the agents' bench.
 *
 * @internal
 */
#[CoversClass(MeilisearchProductIndex::class)]
final class MeilisearchProductIndexTest extends TestCase
{
    /** @var list<string> */
    private array $requests = [];

    private int $taskUid = 0;

    private string $taskStatus = 'succeeded';

    public function testReplaceAllAppliesTheSettingsEmptiesAndFillsEachIndex(): void
    {
        $this->index()->replaceAll(
            [new SearchDocument('SRK25ZSP-W1', 'Mural Premium 2,5 kW R32', 'mural-premium', true, ProductKind::Finished)],
            [],
        );

        $this->assertSame([
            'PATCH /indexes/products/settings',
            'GET /tasks/1',
            'DELETE /indexes/products/documents',
            'GET /tasks/2',
            'POST /indexes/products/documents?primaryKey=id',
            'GET /tasks/3',
            'PATCH /indexes/categories/settings',
            'GET /tasks/4',
            'DELETE /indexes/categories/documents',
            'GET /tasks/5',
        ], $this->requests);
    }

    public function testUpsertAddsOrReplacesTheProducts(): void
    {
        $this->index()->upsert([new SearchDocument('YK-OLD', 'Ancien', 'ancien', false)]);

        $this->assertSame(['POST /indexes/products/documents?primaryKey=id', 'GET /tasks/1'], $this->requests);
    }

    public function testUpsertWithoutProductsWritesNothing(): void
    {
        $this->index()->upsert([]);

        $this->assertSame([], $this->requests);
    }

    public function testAFailedTaskStopsTheRebuild(): void
    {
        $this->taskStatus = 'failed';

        try {
            $this->index()->replaceAll([], [new CategoryDocument('clim_murale', 'Climatiseurs muraux', 'muraux', ProductKind::Finished)]);
            $this->fail('SearchUnavailable expected.');
        } catch (SearchUnavailable) {
            $this->assertSame(['PATCH /indexes/products/settings', 'GET /tasks/1'], $this->requests);
        }
    }

    private function index(): MeilisearchProductIndex
    {
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            $path = (string) substr($url, strlen('http://meilisearch:7700'));
            $this->requests[] = $method . ' ' . $path;
            if (str_starts_with($path, '/tasks/')) {
                return new MockResponse(json_encode(['uid' => $this->taskUid, 'status' => $this->taskStatus, 'error' => ['message' => 'boom']], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode(['taskUid' => ++$this->taskUid], \JSON_THROW_ON_ERROR), ['http_code' => 202]);
        });

        return new MeilisearchProductIndex(new MeilisearchClient($http, 'http://meilisearch:7700', 'cle'), new MeilisearchIndexes());
    }
}
