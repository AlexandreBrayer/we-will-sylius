<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use App\Yack\Catalog\Domain\CatalogFilter;
use App\Yack\Catalog\Domain\InvalidCatalogFilter;

/**
 * Query parameters of the catalogue filters (shop API products and catalog-facets):
 * `yack_brand`, `yack_range`, `yack_power_min`, `yack_power_max` (kW, bounds included),
 * `yack_refrigerant`. Each one takes a single value.
 */
final class CatalogFilterQuery
{
    public const BRAND = 'yack_brand';
    public const RANGE = 'yack_range';
    public const POWER_MIN = 'yack_power_min';
    public const POWER_MAX = 'yack_power_max';
    public const REFRIGERANT = 'yack_refrigerant';

    private const FIELDS = [
        CatalogFilter::FIELD_POWER_MIN => self::POWER_MIN,
        CatalogFilter::FIELD_POWER_MAX => self::POWER_MAX,
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
                self::single($query, self::BRAND),
                self::single($query, self::RANGE),
                self::single($query, self::POWER_MIN),
                self::single($query, self::POWER_MAX),
                self::single($query, self::REFRIGERANT),
            );
        } catch (InvalidCatalogFilter $exception) {
            $parameter = self::FIELDS[$exception->field] ?? $exception->field;

            throw new InvalidCatalogFilter($exception->field, sprintf('%s : %s', $parameter, $exception->getMessage()));
        }
    }

    /** @param array<mixed> $query */
    private static function single(array $query, string $parameter): ?string
    {
        $value = $query[$parameter] ?? null;
        if (null === $value || is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new InvalidCatalogFilter($parameter, sprintf('%s : une seule valeur attendue.', $parameter));
    }
}
