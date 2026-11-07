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
    public function testReadsTheFiveParameters(): void
    {
        $filter = CatalogFilterQuery::filter([
            'yack_type' => 'product',
            'yack_brand' => 'Yack',
            'yack_range' => 'Evolys',
            'yack_power_min' => '6',
            'yack_power_max' => 9,
            'yack_refrigerant' => 'R32',
        ]);

        $this->assertSame('Yack', $filter->brand);
        $this->assertSame('Evolys', $filter->range);
        $this->assertSame(6.0, $filter->powerMin);
        $this->assertSame(9.0, $filter->powerMax);
        $this->assertSame('R32', $filter->refrigerant);
    }

    public function testNoParameter(): void
    {
        $this->assertTrue(CatalogFilterQuery::filter(['page' => '2'])->isEmpty());
    }

    public function testPowerThatIsNotANumberNamesTheParameter(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_power_min : Puissance minimale invalide : « abc » (nombre de kW positif attendu).');

        CatalogFilterQuery::filter(['yack_power_min' => 'abc']);
    }

    public function testMaxParameter(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_power_max : Puissance maximale invalide');

        CatalogFilterQuery::filter(['yack_power_max' => 'beaucoup']);
    }

    public function testSeveralValuesAreRefused(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('yack_refrigerant : une seule valeur attendue.');

        CatalogFilterQuery::filter(['yack_refrigerant' => ['R32', 'R290']]);
    }
}
