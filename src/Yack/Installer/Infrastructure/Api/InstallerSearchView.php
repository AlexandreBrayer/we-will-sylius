<?php

declare(strict_types=1);

namespace App\Yack\Installer\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/**
 * "Trouver un installateur":
 * - GET /api/v2/shop/yack/installers?sector=&range=&activity=  matching installers (map markers included)
 * - GET /api/v2/shop/yack/installers/filters                   values of the three filters
 * Unknown filter values are ignored.
 */
#[ApiResource(
    shortName: 'YackInstallers',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/installers',
            provider: InstallerProvider::class,
            name: 'app_yack_api_shop_installer_search',
        ),
        new Get(
            uriTemplate: '/shop/yack/installers/filters',
            output: InstallerFiltersView::class,
            provider: InstallerProvider::class,
            name: InstallerProvider::FILTERS_OPERATION,
        ),
    ],
)]
final readonly class InstallerSearchView
{
    /**
     * @param array{sector: string|null, range: string|null, activity: string|null} $criteria applied filters
     * @param list<array<string, mixed>> $installers
     */
    public function __construct(
        public array $criteria,
        public int $count,
        public array $installers,
    ) {
    }
}
