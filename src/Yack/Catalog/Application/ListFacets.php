<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\CatalogFacetSource;
use App\Yack\Catalog\Domain\CatalogFilter;
use App\Yack\Catalog\Domain\ProductFacetValues;
use App\Yack\Catalog\Domain\ProductKind;

/**
 * Facets of a shop listing: for each value of brand, range, power band and refrigerant, the
 * number of products of the listing that have it. A facet is counted with the other facets'
 * filters applied, not its own: choosing R32 still shows how many products are in R290.
 */
final readonly class ListFacets
{
    private const LABELS = [
        CatalogFilter::FACET_BRAND => 'Marque',
        CatalogFilter::FACET_RANGE => 'Gamme',
        CatalogFilter::FACET_POWER => 'Puissance',
        CatalogFilter::FACET_REFRIGERANT => 'Fluide frigorigène',
    ];

    public function __construct(private CatalogFacetSource $source)
    {
    }

    /**
     * @return list<array{code: string, label: string, values: list<array{value: string, label: string, count: int}>}>
     */
    public function __invoke(?ProductKind $kind, CatalogFilter $filter): array
    {
        $products = $this->source->listedProducts($kind);

        $facets = [];
        foreach (self::LABELS as $code => $label) {
            $facets[] = ['code' => $code, 'label' => $label, 'values' => $this->values($code, $products, $filter->without($code))];
        }

        return $facets;
    }

    /**
     * @param list<ProductFacetValues> $products
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    private function values(string $facet, array $products, CatalogFilter $filter): array
    {
        /** @var array<string, array{value: string, label: string, count: int}> $values */
        $values = [];
        foreach ($products as $product) {
            $value = self::valueOf($facet, $product);
            if (null === $value) {
                continue;
            }

            $key = mb_strtolower($value);
            $values[$key] ??= ['value' => $value, 'label' => self::labelOf($facet, $value), 'count' => 0];
            if ($filter->matches($product)) {
                ++$values[$key]['count'];
            }
        }

        if (CatalogFilter::FACET_POWER === $facet) {
            $order = array_keys(CatalogFilter::POWER_BANDS);
            uasort($values, static fn (array $a, array $b): int => array_search($a['value'], $order, true) <=> array_search($b['value'], $order, true));
        } else {
            uasort($values, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        }

        return array_values($values);
    }

    private static function valueOf(string $facet, ProductFacetValues $product): ?string
    {
        $value = match ($facet) {
            CatalogFilter::FACET_BRAND => $product->brand,
            CatalogFilter::FACET_RANGE => $product->range,
            CatalogFilter::FACET_REFRIGERANT => $product->refrigerant,
            default => null === $product->powerKw ? null : CatalogFilter::powerBandOf($product->powerKw),
        };
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }

    private static function labelOf(string $facet, string $value): string
    {
        return CatalogFilter::FACET_POWER === $facet ? (CatalogFilter::POWER_BANDS[$value]['label'] ?? $value) : $value;
    }
}
