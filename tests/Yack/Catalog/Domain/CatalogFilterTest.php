<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\CatalogFilter;
use App\Yack\Catalog\Domain\InvalidCatalogFilter;
use App\Yack\Catalog\Domain\ProductFacetValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CatalogFilter::class)]
final class CatalogFilterTest extends TestCase
{
    public function testEmptyInputIsNoFilter(): void
    {
        $this->assertTrue(CatalogFilter::fromInput(' ', null, '', null, '')->isEmpty());
        $this->assertTrue(CatalogFilter::none()->isEmpty());
        $this->assertTrue(CatalogFilter::none()->matches(new ProductFacetValues()));
    }

    public function testValuesAreTrimmedAndPowersParsed(): void
    {
        $filter = CatalogFilter::fromInput(' Yack ', 'Evolys', '6', '8,5', 'R32');

        $this->assertSame('Yack', $filter->brand);
        $this->assertSame('Evolys', $filter->range);
        $this->assertSame(6.0, $filter->powerMin);
        $this->assertSame(8.5, $filter->powerMax);
        $this->assertSame('R32', $filter->refrigerant);
        $this->assertFalse($filter->isEmpty());
    }

    #[DataProvider('provideInvalidPowers')]
    public function testInvalidPowerIsRefused(string $message, ?string $min, ?string $max): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage($message);

        CatalogFilter::fromInput(powerMin: $min, powerMax: $max);
    }

    /**
     * @return \Iterator<string, array{string, string|null, string|null}>
     */
    public static function provideInvalidPowers(): \Iterator
    {
        yield 'min not a number' => ['Puissance minimale invalide : « abc » (nombre de kW positif attendu).', 'abc', null];
        yield 'max negative' => ['Puissance maximale invalide : « -2 » (nombre de kW positif attendu).', null, '-2'];
        yield 'min above max' => ['La puissance minimale (9 kW) dépasse la puissance maximale (6,5 kW).', '9', '6.5'];
    }

    public function testInvalidPowerNamesItsField(): void
    {
        try {
            CatalogFilter::fromInput(powerMax: 'x');
            $this->assertTrue(false, 'exception expected');
        } catch (InvalidCatalogFilter $exception) {
            $this->assertSame(CatalogFilter::FIELD_POWER_MAX, $exception->field);
        }
    }

    #[DataProvider('provideMatches')]
    public function testMatches(bool $expected, CatalogFilter $filter, ProductFacetValues $product): void
    {
        $this->assertSame($expected, $filter->matches($product));
    }

    /**
     * @return \Iterator<string, array{bool, CatalogFilter, ProductFacetValues}>
     */
    public static function provideMatches(): \Iterator
    {
        $evolys8 = new ProductFacetValues('Yack', 'Evolys', 8.0, 'R32');
        $part = new ProductFacetValues('Yack', 'Evolys');

        yield 'refrigerant, case insensitive' => [true, CatalogFilter::fromInput(refrigerant: 'r32'), $evolys8];
        yield 'other refrigerant' => [false, CatalogFilter::fromInput(refrigerant: 'R290'), $evolys8];
        yield 'brand and range' => [true, CatalogFilter::fromInput('yack', 'EVOLYS'), $evolys8];
        yield 'other brand' => [false, CatalogFilter::fromInput('Aerys'), $evolys8];
        yield 'other range' => [false, CatalogFilter::fromInput(range: 'Kaze'), $evolys8];
        yield 'no refrigerant on the product' => [false, CatalogFilter::fromInput(refrigerant: 'R32'), $part];
        yield 'power bounds included (min)' => [true, CatalogFilter::fromInput(powerMin: '8', powerMax: '9'), $evolys8];
        yield 'power bounds included (max)' => [true, CatalogFilter::fromInput(powerMin: '6', powerMax: '8'), $evolys8];
        yield 'power below min' => [false, CatalogFilter::fromInput(powerMin: '9'), $evolys8];
        yield 'power above max' => [false, CatalogFilter::fromInput(powerMax: '7.5'), $evolys8];
        yield 'power filter, product without power' => [false, CatalogFilter::fromInput(powerMin: '0'), $part];
    }

    public function testWithout(): void
    {
        $filter = CatalogFilter::fromInput('Yack', 'Evolys', '6', '9', 'R32');

        $withoutPower = $filter->without(CatalogFilter::FACET_POWER);
        $this->assertNull($withoutPower->powerMin);
        $this->assertNull($withoutPower->powerMax);
        $this->assertSame('R32', $withoutPower->refrigerant);

        $this->assertNull($filter->without(CatalogFilter::FACET_BRAND)->brand);
        $this->assertNull($filter->without(CatalogFilter::FACET_RANGE)->range);
        $this->assertNull($filter->without(CatalogFilter::FACET_REFRIGERANT)->refrigerant);
        $this->assertSame(6.0, $filter->without(CatalogFilter::FACET_REFRIGERANT)->powerMin);
    }

    #[DataProvider('providePowerBands')]
    public function testPowerBandOf(string $expected, float $powerKw): void
    {
        $this->assertSame($expected, CatalogFilter::powerBandOf($powerKw));
    }

    /**
     * @return \Iterator<string, array{string, float}>
     */
    public static function providePowerBands(): \Iterator
    {
        yield '0' => ['0-3', 0.0];
        yield '2.6' => ['0-3', 2.6];
        yield '3, lower bound included' => ['3-6', 3.0];
        yield '5.3' => ['3-6', 5.3];
        yield '6, upper bound excluded' => ['6-9', 6.0];
        yield '8' => ['6-9', 8.0];
        yield '9' => ['9-12', 9.0];
        yield '12, last bounded band includes its upper bound' => ['9-12', 12.0];
        yield '12.5' => ['12+', 12.5];
        yield '16' => ['12+', 16.0];
    }
}
