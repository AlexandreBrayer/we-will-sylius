<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\CategoryDocument;
use App\Yack\Catalog\Domain\ProductSearchIndex;
use App\Yack\Catalog\Domain\SearchDocument;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Adapter: writes the products and categories into Meilisearch and waits for each task, so the
 * caller knows the index is up to date (or gets a SearchUnavailable). A full rebuild applies the
 * settings, empties the index and adds the documents; Meilisearch runs these tasks in order.
 */
#[AsAlias(ProductSearchIndex::class)]
final readonly class MeilisearchProductIndex implements ProductSearchIndex
{
    public function __construct(
        private MeilisearchClient $client,
        private MeilisearchIndexes $indexes,
    ) {
    }

    public function replaceAll(array $products, array $categories): void
    {
        $this->rebuild(
            $this->indexes->products(),
            MeilisearchIndexes::productSettings(),
            array_map(MeilisearchIndexes::productDocument(...), $products),
        );
        $this->rebuild(
            $this->indexes->categories(),
            MeilisearchIndexes::categorySettings(),
            array_map(MeilisearchIndexes::categoryDocument(...), $categories),
        );
    }

    public function upsert(array $products): void
    {
        if ([] === $products) {
            return;
        }

        $this->write('POST', sprintf('/indexes/%s/documents?primaryKey=id', $this->indexes->products()), array_map(MeilisearchIndexes::productDocument(...), $products));
    }

    /**
     * @param array<string, mixed>       $settings
     * @param list<array<string, mixed>> $documents
     */
    private function rebuild(string $index, array $settings, array $documents): void
    {
        $this->write('PATCH', sprintf('/indexes/%s/settings', $index), $settings);
        $this->write('DELETE', sprintf('/indexes/%s/documents', $index));
        if ([] !== $documents) {
            $this->write('POST', sprintf('/indexes/%s/documents?primaryKey=id', $index), $documents);
        }
    }

    /**
     * @param array<mixed>|null $body
     */
    private function write(string $method, string $path, ?array $body = null): void
    {
        $this->client->wait($this->client->request($method, $path, $body, MeilisearchClient::INDEXING_TIMEOUT));
    }
}
