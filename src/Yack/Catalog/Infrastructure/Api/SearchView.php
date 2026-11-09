<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/**
 * Shop search:
 * - GET /api/v2/shop/yack/search?q=&limit=&page=&type=product|part  codes of the matching products, most
 *   relevant first, and their total (the cards, prices included, come from /api/v2/shop/products/{code})
 * - GET /api/v2/shop/yack/search/suggest?q=                          products and categories proposed while typing
 * `engine` tells which engine answered (meilisearch, or database when Meilisearch is off or down).
 */
#[ApiResource(
    shortName: 'YackSearch',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/search',
            provider: SearchProvider::class,
            name: SearchProvider::SEARCH_OPERATION,
        ),
        new Get(
            uriTemplate: '/shop/yack/search/suggest',
            output: SuggestionsView::class,
            provider: SearchProvider::class,
            name: SearchProvider::SUGGEST_OPERATION,
        ),
    ],
)]
final readonly class SearchView
{
    /**
     * @param list<string> $codes
     */
    public function __construct(
        public string $query,
        public string $engine,
        public int $total,
        public int $page,
        public int $limit,
        public array $codes,
    ) {
    }
}
