<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Catalog\Application\SearchCatalog;
use App\Yack\Catalog\Domain\CategorySuggestion;
use App\Yack\Catalog\Domain\ProductSuggestion;
use App\Yack\Catalog\Domain\SearchQuery;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<SearchView|SuggestionsView>
 */
final readonly class SearchProvider implements ProviderInterface
{
    public const SEARCH_OPERATION = 'app_yack_api_shop_search';
    public const SUGGEST_OPERATION = 'app_yack_api_shop_search_suggest';

    /** Thumbnail of the product suggestions (same filter as the product cards of the shop). */
    public const THUMBNAIL_FILTER = 'sylius_shop_product_thumbnail';

    public function __construct(
        private SearchCatalog $searchCatalog,
        #[Autowire(service: 'liip_imagine.cache.manager')]
        private CacheManager $imagineCacheManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SearchView|SuggestionsView
    {
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;
        $text = $query?->getString('q') ?? '';

        if (self::SUGGEST_OPERATION === $operation->getName()) {
            $suggestions = $this->searchCatalog->suggest($text);

            return new SuggestionsView(
                (new SearchQuery($text))->text,
                $suggestions->engine->value,
                array_map($this->product(...), $suggestions->products),
                array_map(static fn (CategorySuggestion $category): array => ['code' => $category->code, 'name' => $category->name, 'slug' => $category->slug], $suggestions->categories),
            );
        }

        $search = SearchQuery::fromInput($text, $query?->getString('limit'), $query?->getString('page'), $query?->getString('type'), $query?->getString('category'));
        $result = $this->searchCatalog->search($search);

        return new SearchView($search->text, $result->engine->value, $result->total, $search->page, $search->perPage, $result->codes);
    }

    /** @return array{code: string, name: string, slug: string, brand: string|null, image: string|null} */
    private function product(ProductSuggestion $product): array
    {
        return [
            'code' => $product->code,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'image' => null === $product->imagePath ? null : $this->imagineCacheManager->getBrowserPath($product->imagePath, self::THUMBNAIL_FILTER),
        ];
    }
}
