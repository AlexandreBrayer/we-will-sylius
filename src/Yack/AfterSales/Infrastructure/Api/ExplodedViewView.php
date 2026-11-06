<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/**
 * GET /api/v2/shop/yack/sav/exploded-views/{id} — SVG drawing, hotspots and spare parts.
 * Part prices only for customers allowed to see them (ECOM), null otherwise.
 * Add to cart (ECOM): native POST /shop/orders/{token}/items with the part "variant" IRI.
 */
#[ApiResource(
    shortName: 'YackSavExplodedView',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/sav/exploded-views/{id}',
            requirements: ['id' => '\d+'],
            provider: AfterSalesProvider::class,
            name: 'app_yack_api_shop_sav_exploded_view_get',
        ),
    ],
)]
final readonly class ExplodedViewView
{
    /**
     * @param array{serviceCode: string, name: string, family: string} $model
     * @param list<array{id: int, name: string}> $views all the views of the model (navigation)
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,
        public string $name,
        public string $imagePath,
        public string $imageUrl,
        public array $model,
        public array $views,
        public array $items,
    ) {
    }
}
