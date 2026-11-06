<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/**
 * GET /api/v2/shop/yack/sav/serials/{serial} — serial number (or ServiceCode) → models + warranty.
 * 404 when nothing matches.
 */
#[ApiResource(
    shortName: 'YackSavSerial',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/sav/serials/{query}',
            provider: AfterSalesProvider::class,
            name: 'app_yack_api_shop_sav_serial_get',
        ),
    ],
)]
final readonly class SerialLookupView
{
    /**
     * @param array{serialNumber: string, commissionedAt: string, partsCoverageEnd: string, compressorCoverageEnd: string, partsCovered: bool, compressorCovered: bool, source: string}|null $warranty
     * @param list<array<string, mixed>> $models
     */
    public function __construct(
        /** What was typed. */
        #[ApiProperty(identifier: true)]
        public string $query,
        /** Normalised serial number, null when a ServiceCode was typed. */
        public ?string $serial,
        public bool $isServiceCode,
        public ?array $warranty,
        public array $models,
    ) {
    }
}
