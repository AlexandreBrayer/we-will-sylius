<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Api;

use App\Yack\Catalog\Domain\InvalidCatalogFilter;
use App\Yack\Catalog\Infrastructure\Api\CatalogFilterQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CatalogFilterQuery::class)]
final class CatalogFilterQueryTest extends TestCase
{
    public function testReadsTheParameters(): void
    {
        $filter = CatalogFilterQuery::filter([
            'yack_type' => 'product',
            'yack_brand' => 'Yack',
            'yack_range' => 'Evolys',
            'yack_power_band' => '6-9',
            'yack_refrigerant' => 'R32',
            'yack_price_min' => '500',
            'yack_price_max' => 2000,
        ], true);

        $this->assertSame(['Yack'], $filter->brands);
        $this->assertSame(['Evolys'], $filter->ranges);
        $this->assertSame(['6-9'], $filter->powerBands);
        $this->assertSame(['R32'], $filter->refrigerants);
        $this->assertSame(500.0, $filter->priceMin);
        $this->assertSame(2000.0, $filter->priceMax);
    }

    public function testPricesAreIgnoredForACustomerWhoDoesNotSeeThem(): void
    {
        $filter = CatalogFilterQuery::filter(['yack_brand' => 'MHI', 'yack_price_min' => '500', 'yack_price_max' => 'abc'], false);

        $this->assertSame(['MHI'], $filter->brands);
        $this->assertNull($filter->priceMin);
        $this->assertNull($filter->priceMax);
        $this->assertTrue(CatalogFilterQuery::filter(['yack_price_min' => '500'], false)->isEmpty());
    }

    public function testInvalidPriceNamesTheParameter(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_price_max : Prix maximum invalide : « beaucoup » (montant positif en euros attendu).');

        CatalogFilterQuery::filter(['yack_price_max' => 'beaucoup'], true);
    }

    public function testMinimumPriceAboveMaximum(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage("yack_price_min : Le prix minimum (3\u{202f}000 €) dépasse le prix maximum (10 €).");

        CatalogFilterQuery::filter(['yack_price_min' => '3000', 'yack_price_max' => '10'], true);
    }

    public function testSeveralPricesAreRefused(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_price_min : une seule valeur attendue.');

        CatalogFilterQuery::filter(['yack_price_min' => ['10', '20']], true);
    }

    public function testSeveralValuesAsAnArray(): void
    {
        $filter = CatalogFilterQuery::filter([
            'yack_brand' => ['MHI', 'RHOSS', 'mhi'],
            'yack_power_band' => ['0-3', '12+'],
            'yack_refrigerant' => ['R32', 290],
        ], false);

        $this->assertSame(['MHI', 'RHOSS'], $filter->brands);
        $this->assertSame([], $filter->ranges);
        $this->assertSame(['0-3', '12+'], $filter->powerBands);
        $this->assertSame(['R32', '290'], $filter->refrigerants);
    }

    public function testNoParameter(): void
    {
        $this->assertTrue(CatalogFilterQuery::filter(['page' => '2'], true)->isEmpty());
    }

    public function testUnknownPowerBandNamesTheParameter(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_power_band : Tranche de puissance inconnue : « 6-12 » (attendu : 0-3, 3-6, 6-9, 9-12, 12+).');

        CatalogFilterQuery::filter(['yack_power_band' => ['0-3', '6-12']], false);
    }

    public function testNestedValuesAreRefused(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_refrigerant : liste de valeurs attendue (paramètre[]=valeur).');

        CatalogFilterQuery::filter(['yack_refrigerant' => [['R32']]], false);
    }
}
