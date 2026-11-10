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
        $this->assertTrue(CatalogFilter::fromInput([' '], [], [''], [])->isEmpty());
        $this->assertTrue(CatalogFilter::none()->isEmpty());
        $this->assertTrue(CatalogFilter::none()->matches(new ProductFacetValues()));
    }

    public function testValuesAreTrimmedAndDeduplicatedCaseAside(): void
    {
        $filter = CatalogFilter::fromInput([' Yack ', 'MHI', 'yack', ''], ['Evolys'], ['6-9', ' 12+ ', '6-9'], ['R32']);

        $this->assertSame(['Yack', 'MHI'], $filter->brands);
        $this->assertSame(['Evolys'], $filter->ranges);
        $this->assertSame(['6-9', '12+'], $filter->powerBands);
        $this->assertSame(['R32'], $filter->refrigerants);
        $this->assertFalse($filter->isEmpty());
    }

    public function testUnknownPowerBandIsRefused(): void
    {
        try {
            CatalogFilter::fromInput(powerBands: ['6-9', '5-7']);
            $this->assertTrue(false, 'exception expected');
        } catch (InvalidCatalogFilter $exception) {
            $this->assertSame(CatalogFilter::FACET_POWER, $exception->field);
            $this->assertSame('Tranche de puissance inconnue : « 5-7 » (attendu : 0-3, 3-6, 6-9, 9-12, 12+).', $exception->getMessage());
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

        yield 'refrigerant, case insensitive' => [true, CatalogFilter::fromInput(refrigerants: ['r32']), $evolys8];
        yield 'other refrigerant' => [false, CatalogFilter::fromInput(refrigerants: ['R290']), $evolys8];
        yield 'brand and range' => [true, CatalogFilter::fromInput(['yack'], ['EVOLYS']), $evolys8];
        yield 'other brand' => [false, CatalogFilter::fromInput(['Aerys']), $evolys8];
        yield 'one of the brands' => [true, CatalogFilter::fromInput(['MHI', 'Yack']), $evolys8];
        yield 'none of the brands' => [false, CatalogFilter::fromInput(['MHI', 'RHOSS']), $evolys8];
        yield 'one value in each facet' => [true, CatalogFilter::fromInput(['MHI', 'Yack'], ['Kaze', 'Evolys'], ['0-3', '6-9'], ['R290', 'R32']), $evolys8];
        yield 'all facets required' => [false, CatalogFilter::fromInput(['MHI', 'Yack'], ['Kaze', 'Evolys'], ['0-3', '3-6'], ['R290', 'R32']), $evolys8];
        yield 'other range' => [false, CatalogFilter::fromInput(ranges: ['Kaze']), $evolys8];
        yield 'no refrigerant on the product' => [false, CatalogFilter::fromInput(refrigerants: ['R32']), $part];
        yield 'power band' => [true, CatalogFilter::fromInput(powerBands: ['6-9']), $evolys8];
        yield 'one of the power bands' => [true, CatalogFilter::fromInput(powerBands: ['0-3', '6-9', '12+']), $evolys8];
        yield 'other power bands' => [false, CatalogFilter::fromInput(powerBands: ['3-6', '9-12']), $evolys8];
        yield 'power band, 12 kW in 9-12' => [true, CatalogFilter::fromInput(powerBands: ['9-12']), new ProductFacetValues(powerKw: 12.0)];
        yield 'power band, 12 kW not in 12+' => [false, CatalogFilter::fromInput(powerBands: ['12+']), new ProductFacetValues(powerKw: 12.0)];
        yield 'power band, product without power' => [false, CatalogFilter::fromInput(powerBands: ['0-3']), $part];
    }

    public function testWithout(): void
    {
        $filter = CatalogFilter::fromInput(['Yack', 'MHI'], ['Evolys'], ['6-9', '9-12'], ['R32']);

        $withoutPower = $filter->without(CatalogFilter::FACET_POWER);
        $this->assertSame([], $withoutPower->powerBands);
        $this->assertSame(['R32'], $withoutPower->refrigerants);
        $this->assertSame(['Yack', 'MHI'], $withoutPower->brands);

        $this->assertSame([], $filter->without(CatalogFilter::FACET_BRAND)->brands);
        $this->assertSame([], $filter->without(CatalogFilter::FACET_RANGE)->ranges);
        $this->assertSame([], $filter->without(CatalogFilter::FACET_REFRIGERANT)->refrigerants);
        $this->assertSame(['6-9', '9-12'], $filter->without(CatalogFilter::FACET_REFRIGERANT)->powerBands);
    }

    #[DataProvider('providePowerBandBounds')]
    public function testPowerBandBoundsHoldTheBandPowers(string $band): void
    {
        $bounds = CatalogFilter::powerBandBounds($band);
        foreach ([0.0, 2.9, 3.0, 5.9, 6.0, 8.9, 9.0, 11.9, 12.0, 12.1, 40.0] as $power) {
            $inBounds = (null === $bounds['min'] || ($bounds['minIncluded'] ? $power >= $bounds['min'] : $power > $bounds['min']))
                && (null === $bounds['max'] || ($bounds['maxIncluded'] ? $power <= $bounds['max'] : $power < $bounds['max']));
            $this->assertSame(CatalogFilter::powerBandOf($power) === $band, $inBounds, sprintf('%s kW, band %s', $power, $band));
        }
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function providePowerBandBounds(): \Iterator
    {
        foreach (array_keys(CatalogFilter::POWER_BANDS) as $band) {
            yield $band => [$band];
        }
    }

    public function testPowerBandBoundsOfTheOpenBand(): void
    {
        $this->assertSame(['min' => 12.0, 'minIncluded' => false, 'max' => null, 'maxIncluded' => false], CatalogFilter::powerBandBounds('12+'));
        $this->assertSame(['min' => null, 'minIncluded' => true, 'max' => 3.0, 'maxIncluded' => false], CatalogFilter::powerBandBounds('0-3'));
    }

    public function testPowerBandBoundsOfAnUnknownBand(): void
    {
        $this->expectException(InvalidCatalogFilter::class);
        $this->expectExceptionMessage('Tranche de puissance inconnue : « 4-5 ».');

        CatalogFilter::powerBandBounds('4-5');
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
