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
    public function testReadsTheFourParameters(): void
    {
        $filter = CatalogFilterQuery::filter([
            'yack_type' => 'product',
            'yack_brand' => 'Yack',
            'yack_range' => 'Evolys',
            'yack_power_band' => '6-9',
            'yack_refrigerant' => 'R32',
        ]);

        $this->assertSame(['Yack'], $filter->brands);
        $this->assertSame(['Evolys'], $filter->ranges);
        $this->assertSame(['6-9'], $filter->powerBands);
        $this->assertSame(['R32'], $filter->refrigerants);
    }

    public function testSeveralValuesAsAnArray(): void
    {
        $filter = CatalogFilterQuery::filter([
            'yack_brand' => ['MHI', 'RHOSS', 'mhi'],
            'yack_power_band' => ['0-3', '12+'],
            'yack_refrigerant' => ['R32', 290],
        ]);

        $this->assertSame(['MHI', 'RHOSS'], $filter->brands);
        $this->assertSame([], $filter->ranges);
        $this->assertSame(['0-3', '12+'], $filter->powerBands);
        $this->assertSame(['R32', '290'], $filter->refrigerants);
    }

    public function testNoParameter(): void
    {
        $this->assertTrue(CatalogFilterQuery::filter(['page' => '2'])->isEmpty());
    }

    public function testUnknownPowerBandNamesTheParameter(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_power_band : Tranche de puissance inconnue : « 6-12 » (attendu : 0-3, 3-6, 6-9, 9-12, 12+).');

        CatalogFilterQuery::filter(['yack_power_band' => ['0-3', '6-12']]);
    }

    public function testNestedValuesAreRefused(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_refrigerant : liste de valeurs attendue (paramètre[]=valeur).');

        CatalogFilterQuery::filter(['yack_refrigerant' => [['R32']]]);
    }
}
