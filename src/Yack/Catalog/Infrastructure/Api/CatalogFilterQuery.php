<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use App\Yack\Catalog\Domain\CatalogFilter;
use App\Yack\Catalog\Domain\InvalidCatalogFilter;

/**
 * Query parameters of the catalogue filters (shop API products and catalog-facets):
 * `yack_brand`, `yack_range`, `yack_power_band` (band codes of `CatalogFilter::POWER_BANDS`),
 * `yack_refrigerant`. Each one takes one value (`yack_brand=MHI`) or several, as an array in
 * the API Platform way (`yack_brand[]=MHI&yack_brand[]=RHOSS`): any of them within a parameter,
 * all the parameters together.
 */
final class CatalogFilterQuery
{
    public const BRAND = 'yack_brand';
    public const RANGE = 'yack_range';
    public const POWER_BAND = 'yack_power_band';
    public const REFRIGERANT = 'yack_refrigerant';

    private const PARAMETERS = [
        CatalogFilter::FACET_BRAND => self::BRAND,
        CatalogFilter::FACET_RANGE => self::RANGE,
        CatalogFilter::FACET_POWER => self::POWER_BAND,
        CatalogFilter::FACET_REFRIGERANT => self::REFRIGERANT,
    ];

    /**
     * @param array<mixed> $query query parameters (API Platform `filters` context)
     *
     * @throws InvalidCatalogFilter with the message to return in a 400 response
     */
    public static function filter(array $query): CatalogFilter
    {
        try {
            return CatalogFilter::fromInput(
                self::values($query, self::BRAND),
                self::values($query, self::RANGE),
                self::values($query, self::POWER_BAND),
                self::values($query, self::REFRIGERANT),
            );
        } catch (InvalidCatalogFilter $exception) {
            $parameter = self::PARAMETERS[$exception->field] ?? $exception->field;

            throw new InvalidCatalogFilter($exception->field, sprintf('%s : %s', $parameter, $exception->getMessage()));
        }
    }

    /**
     * @param array<mixed> $query
     *
     * @return list<string>
     */
    private static function values(array $query, string $parameter): array
    {
        $value = $query[$parameter] ?? null;
        if (null === $value) {
            return [];
        }

        $values = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            if (!is_string($item) && !is_int($item) && !is_float($item)) {
                throw new InvalidCatalogFilter($parameter, 'liste de valeurs attendue (paramètre[]=valeur).');
            }
            $values[] = (string) $item;
        }

        return $values;
    }
}
