<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/**
 * GET /api/v2/shop/yack/sav/models/{serviceCode} — a model (ServiceCode) and its exploded views.
 */
#[ApiResource(
    shortName: 'YackSavModel',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/sav/models/{serviceCode}',
            provider: AfterSalesProvider::class,
            name: 'app_yack_api_shop_sav_model_get',
        ),
    ],
)]
final readonly class ModelView
{
    /**
     * @param array{code: string|null, slug: string|null, name: string|null}|null $finishedProduct
     * @param list<array{id: int, name: string, imagePath: string, imageUrl: string, partCount: int}> $explodedViews
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $serviceCode,
        public string $name,
        public string $family,
        public int $partCount,
        public ?array $finishedProduct,
        public array $explodedViews,
    ) {
    }
}
