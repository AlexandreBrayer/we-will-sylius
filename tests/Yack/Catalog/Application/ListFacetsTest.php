<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\ListFacets;
use App\Yack\Catalog\Domain\CatalogFacetSource;
use App\Yack\Catalog\Domain\CatalogFilter;
use App\Yack\Catalog\Domain\ProductFacetValues;
use App\Yack\Catalog\Domain\ProductKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ListFacets::class)]
final class ListFacetsTest extends TestCase
{
    private ListFacetsFakeSource $source;

    private ListFacets $listFacets;

    #[\Override]
    protected function setUp(): void
    {
        $this->source = new ListFacetsFakeSource([
            new ProductFacetValues('Yack', 'Evolys', 6.0, 'R32', 1200.0),
            new ProductFacetValues('Yack', 'Evolys', 8.0, 'R32', 1800.0),
            new ProductFacetValues('Aerys', 'Kaze', 2.6, 'R32', 450.0),
            new ProductFacetValues('Calyos', 'Mobil', 2.6, 'R290', 299.9),
            new ProductFacetValues('yack', ' ', 16.0, null, 5200.0),
            new ProductFacetValues(),
        ]);
        $this->listFacets = new ListFacets($this->source);
    }

    public function testFacetsOfTheWholeListing(): void
    {
        $result = ($this->listFacets)(ProductKind::Finished, CatalogFilter::none(), true);
        $facets = $result['facets'];

        $this->assertSame(ProductKind::Finished, $this->source->requestedKind);
        $this->assertSame(['brand', 'range', 'power', 'refrigerant'], array_column($facets, 'code'));
        $this->assertSame(['Marque', 'Gamme', 'Puissance', 'Fluide frigorigène'], array_column($facets, 'label'));
        $this->assertSame([
            ['value' => 'Aerys', 'label' => 'Aerys', 'count' => 1],
            ['value' => 'Calyos', 'label' => 'Calyos', 'count' => 1],
            ['value' => 'Yack', 'label' => 'Yack', 'count' => 3],
        ], $facets[0]['values']);
        $this->assertSame([
            ['value' => 'Evolys', 'label' => 'Evolys', 'count' => 2],
            ['value' => 'Kaze', 'label' => 'Kaze', 'count' => 1],
            ['value' => 'Mobil', 'label' => 'Mobil', 'count' => 1],
        ], $facets[1]['values']);
        $this->assertSame([
            ['value' => '0-3', 'label' => '0 à 3 kW', 'count' => 2],
            ['value' => '6-9', 'label' => '6 à 9 kW', 'count' => 2],
            ['value' => '12+', 'label' => 'Plus de 12 kW', 'count' => 1],
        ], $facets[2]['values']);
        // Natural order: R32 before R290.
        $this->assertSame([
            ['value' => 'R32', 'label' => 'R32', 'count' => 3],
            ['value' => 'R290', 'label' => 'R290', 'count' => 1],
        ], $facets[3]['values']);
        $this->assertSame(['min' => 299.9, 'max' => 5200.0], $result['price']);
    }

    public function testNoPriceBoundsForACustomerWhoDoesNotSeePrices(): void
    {
        $result = ($this->listFacets)(null, CatalogFilter::fromInput(['Yack']), false);

        $this->assertNull($result['price']);
        $this->assertSame([1, 1, 3], array_column($result['facets'][0]['values'], 'count'));
    }

    public function testThePriceRangeFiltersTheCountsButNotItsOwnBounds(): void
    {
        $result = ($this->listFacets)(null, CatalogFilter::fromInput(priceMin: '400', priceMax: '2000'), true);

        // Brand (Aerys, Calyos, Yack): 400 to 2000 euros.
        $this->assertSame([1, 0, 2], array_column($result['facets'][0]['values'], 'count'));
        // Bounds of the whole listing: the range can be widened again.
        $this->assertSame(['min' => 299.9, 'max' => 5200.0], $result['price']);

        // Bounds of the products of the other filters.
        $yack = ($this->listFacets)(null, CatalogFilter::fromInput(['Yack', 'Aerys'], priceMin: '400', priceMax: '2000'), true);
        $this->assertSame(['min' => 450.0, 'max' => 5200.0], $yack['price']);
        $this->assertSame([1, 0, 2], array_column($yack['facets'][0]['values'], 'count'));
        // Power (0-3, 6-9, 12+): Yack or Aerys, 400 to 2000 euros.
        $this->assertSame([1, 2, 0], array_column($yack['facets'][2]['values'], 'count'));
    }

    public function testNoPriceBoundsWithoutAnyPricedProduct(): void
    {
        $result = ($this->listFacets)(null, CatalogFilter::fromInput(['Calyos'], ['Kaze']), true);

        $this->assertNull($result['price']);
    }

    public function testEachFacetIsCountedWithTheOtherFilters(): void
    {
        $facets = ($this->listFacets)(null, CatalogFilter::fromInput(refrigerants: ['R32'], powerBands: ['6-9']), true)['facets'];

        $this->assertNull($this->source->requestedKind);
        // Brand: R32 and 6 to 9 kW.
        $this->assertSame([0, 0, 2], array_column($facets[0]['values'], 'count'));
        // Power: R32 only (its own filter is ignored).
        $this->assertSame([1, 2, 0], array_column($facets[2]['values'], 'count'));
        // Refrigerant (R32, R290): 6 to 9 kW only.
        $this->assertSame([2, 0], array_column($facets[3]['values'], 'count'));
    }

    public function testSeveralValuesOfAFacetAddUp(): void
    {
        // Aerys or Calyos, 0 to 3 kW or 6 to 9 kW.
        $facets = ($this->listFacets)(null, CatalogFilter::fromInput(['Aerys', 'calyos'], powerBands: ['0-3', '6-9']), true)['facets'];

        // Brand (Aerys, Calyos, Yack): power bands only, each brand still countable.
        $this->assertSame([1, 1, 2], array_column($facets[0]['values'], 'count'));
        // Range (Evolys, Kaze, Mobil): Aerys or Calyos, in either band.
        $this->assertSame([0, 1, 1], array_column($facets[1]['values'], 'count'));
        // Power (0-3, 6-9, 12+): Aerys or Calyos only.
        $this->assertSame([2, 0, 0], array_column($facets[2]['values'], 'count'));
        // Refrigerant (R32, R290): both brands, both bands.
        $this->assertSame([1, 1], array_column($facets[3]['values'], 'count'));
    }

    public function testEmptyListing(): void
    {
        $result = (new ListFacets(new ListFacetsFakeSource([])))(ProductKind::SparePart, CatalogFilter::none(), true);

        $this->assertCount(4, $result['facets']);
        $this->assertSame([[], [], [], []], array_column($result['facets'], 'values'));
        $this->assertNull($result['price']);
    }
}

/** Hand-written fake of the port. */
final class ListFacetsFakeSource implements CatalogFacetSource
{
    public ?ProductKind $requestedKind = null;

    /** @param list<ProductFacetValues> $products */
    public function __construct(private readonly array $products)
    {
    }

    public function listedProducts(?ProductKind $kind): array
    {
        $this->requestedKind = $kind;

        return $this->products;
    }
}
