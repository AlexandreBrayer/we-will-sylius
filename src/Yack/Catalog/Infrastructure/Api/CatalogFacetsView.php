<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * GET /api/v2/shop/yack/catalog-facets?yack_type=product|part (+ the catalogue filters):
 * values of brand, range, power band and refrigerant of the listing, with their product count;
 * `price`: lowest and highest prices (euros HT) of the listing, the price range aside, null for a
 * customer who does not see prices or when no product has a price.
 */
#[ApiResource(
    shortName: 'YackCatalogFacets',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/catalog-facets',
            normalizationContext: ['groups' => ['catalog_facet:list']],
            provider: CatalogFacetsProvider::class,
            name: 'app_yack_api_shop_catalog_facets',
        ),
    ],
)]
final readonly class CatalogFacetsView
{
    /**
     * @param string|null                                                                                          $type   applied `yack_type` (null: whole catalogue)
     * @param list<array{code: string, label: string, values: list<array{value: string, label: string, count: int}>}> $facets
     * @param array{min: float, max: float}|null                                                                 $price
     */
    public function __construct(
        #[Groups(['catalog_facet:list'])]
        public ?string $type,
        #[Groups(['catalog_facet:list'])]
        public array $facets,
        #[Groups(['catalog_facet:list'])]
        public ?array $price,
    ) {
    }
}
