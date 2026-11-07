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
            new ProductFacetValues('Yack', 'Evolys', 6.0, 'R32'),
            new ProductFacetValues('Yack', 'Evolys', 8.0, 'R32'),
            new ProductFacetValues('Aerys', 'Kaze', 2.6, 'R32'),
            new ProductFacetValues('Calyos', 'Mobil', 2.6, 'R290'),
            new ProductFacetValues('yack', ' ', 16.0, null),
            new ProductFacetValues(),
        ]);
        $this->listFacets = new ListFacets($this->source);
    }

    public function testFacetsOfTheWholeListing(): void
    {
        $facets = ($this->listFacets)(ProductKind::Finished, CatalogFilter::none());

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
    }

    public function testEachFacetIsCountedWithTheOtherFilters(): void
    {
        $facets = ($this->listFacets)(null, CatalogFilter::fromInput(refrigerant: 'R32', powerMin: '6', powerMax: '9'));

        $this->assertNull($this->source->requestedKind);
        // Brand: R32 and 6 to 9 kW.
        $this->assertSame([0, 0, 2], array_column($facets[0]['values'], 'count'));
        // Power: R32 only (its own filter is ignored).
        $this->assertSame([1, 2, 0], array_column($facets[2]['values'], 'count'));
        // Refrigerant (R32, R290): 6 to 9 kW only.
        $this->assertSame([2, 0], array_column($facets[3]['values'], 'count'));
    }

    public function testEmptyListing(): void
    {
        $facets = (new ListFacets(new ListFacetsFakeSource([])))(ProductKind::SparePart, CatalogFilter::none());

        $this->assertCount(4, $facets);
        $this->assertSame([[], [], [], []], array_column($facets, 'values'));
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
