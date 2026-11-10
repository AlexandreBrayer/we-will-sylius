<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Filters of a catalogue listing: brands, ranges, power bands and refrigerants, and a price range.
 * Each facet holds any number of values: a product matches a facet when it has one of its values,
 * and the filter when it matches every facet that has values and the price range (euros HT, bounds
 * included). Built from what the customer chose: a value that cannot be applied is refused here,
 * before any query. Whether the customer may filter on prices is decided by the caller.
 */
final readonly class CatalogFilter
{
    public const FACET_BRAND = 'brand';
    public const FACET_RANGE = 'range';
    public const FACET_POWER = 'power';
    public const FACET_REFRIGERANT = 'refrigerant';
    public const FACET_PRICE = 'price';

    public const FIELD_PRICE_MIN = 'price_min';
    public const FIELD_PRICE_MAX = 'price_max';

    /** Last band, without upper bound. */
    public const OPEN_POWER_BAND = '12+';

    /**
     * Power bands of the "Puissance" facet, in kW: lower bound included, upper bound excluded,
     * except the last bounded band (9 to 12 kW includes 12 kW); "Plus de 12 kW" is above 12.
     *
     * @var array<string, array{label: string, max: float|null, maxIncluded: bool}>
     */
    public const POWER_BANDS = [
        '0-3' => ['label' => '0 à 3 kW', 'max' => 3.0, 'maxIncluded' => false],
        '3-6' => ['label' => '3 à 6 kW', 'max' => 6.0, 'maxIncluded' => false],
        '6-9' => ['label' => '6 à 9 kW', 'max' => 9.0, 'maxIncluded' => false],
        '9-12' => ['label' => '9 à 12 kW', 'max' => 12.0, 'maxIncluded' => true],
        '12+' => ['label' => 'Plus de 12 kW', 'max' => null, 'maxIncluded' => false],
    ];

    /**
     * @param list<string> $brands
     * @param list<string> $ranges
     * @param list<string> $powerBands codes of POWER_BANDS
     * @param list<string> $refrigerants
     * @param float|null   $priceMin     euros HT, included
     * @param float|null   $priceMax     euros HT, included
     */
    private function __construct(
        public array $brands = [],
        public array $ranges = [],
        public array $powerBands = [],
        public array $refrigerants = [],
        public ?float $priceMin = null,
        public ?float $priceMax = null,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * Raw values chosen by the customer, per facet; blank values are ignored, the same value
     * twice (case aside) counts once. Prices in euros HT, decimal comma or point.
     *
     * @param list<string> $brands
     * @param list<string> $ranges
     * @param list<string> $powerBands codes of POWER_BANDS
     * @param list<string> $refrigerants
     *
     * @throws InvalidCatalogFilter unknown power band, price that is not a positive amount, minimum above maximum
     */
    public static function fromInput(
        array $brands = [],
        array $ranges = [],
        array $powerBands = [],
        array $refrigerants = [],
        ?string $priceMin = null,
        ?string $priceMax = null,
    ): self {
        $bands = self::texts($powerBands);
        foreach ($bands as $band) {
            if (!isset(self::POWER_BANDS[$band])) {
                throw new InvalidCatalogFilter(self::FACET_POWER, sprintf('Tranche de puissance inconnue : « %s » (attendu : %s).', $band, implode(', ', array_keys(self::POWER_BANDS))));
            }
        }

        $min = self::price(self::FIELD_PRICE_MIN, 'Prix minimum', $priceMin);
        $max = self::price(self::FIELD_PRICE_MAX, 'Prix maximum', $priceMax);
        if (null !== $min && null !== $max && $min > $max) {
            throw new InvalidCatalogFilter(self::FIELD_PRICE_MIN, sprintf('Le prix minimum (%s €) dépasse le prix maximum (%s €).', self::euros($min), self::euros($max)));
        }

        return new self(self::texts($brands), self::texts($ranges), $bands, self::texts($refrigerants), $min, $max);
    }

    public function isEmpty(): bool
    {
        return [] === $this->brands && [] === $this->ranges && [] === $this->powerBands && [] === $this->refrigerants
            && null === $this->priceMin && null === $this->priceMax;
    }

    /** Same filter without the values of one facet, or without the price range (its own values stay countable). */
    public function without(string $facet): self
    {
        return new self(
            self::FACET_BRAND === $facet ? [] : $this->brands,
            self::FACET_RANGE === $facet ? [] : $this->ranges,
            self::FACET_POWER === $facet ? [] : $this->powerBands,
            self::FACET_REFRIGERANT === $facet ? [] : $this->refrigerants,
            self::FACET_PRICE === $facet ? null : $this->priceMin,
            self::FACET_PRICE === $facet ? null : $this->priceMax,
        );
    }

    public function matches(ProductFacetValues $product): bool
    {
        return self::oneOf($this->brands, $product->brand)
            && self::oneOf($this->ranges, $product->range)
            && self::oneOf($this->refrigerants, $product->refrigerant)
            && ([] === $this->powerBands || (null !== $product->powerKw && \in_array(self::powerBandOf($product->powerKw), $this->powerBands, true)))
            && $this->matchesPrice($product->price);
    }

    /** Code of the power band holding this power (see POWER_BANDS). */
    public static function powerBandOf(float $powerKw): string
    {
        foreach (self::POWER_BANDS as $code => $band) {
            if (null !== $band['max'] && ($powerKw < $band['max'] || ($band['maxIncluded'] && $powerKw <= $band['max']))) {
                return $code;
            }
        }

        return self::OPEN_POWER_BAND;
    }

    /**
     * Powers held by a band, as `powerBandOf()` assigns them: from the upper bound of the previous
     * band (excluded when that band includes it; none for the first band) to its own upper bound.
     *
     * @return array{min: float|null, minIncluded: bool, max: float|null, maxIncluded: bool}
     *
     * @throws InvalidCatalogFilter unknown band
     */
    public static function powerBandBounds(string $code): array
    {
        $previous = null;
        foreach (self::POWER_BANDS as $bandCode => $band) {
            if ($bandCode === $code) {
                return [
                    'min' => null === $previous ? null : $previous['max'],
                    'minIncluded' => null === $previous || !$previous['maxIncluded'],
                    'max' => $band['max'],
                    'maxIncluded' => $band['maxIncluded'],
                ];
            }
            $previous = $band;
        }

        throw new InvalidCatalogFilter(self::FACET_POWER, sprintf('Tranche de puissance inconnue : « %s ».', $code));
    }

    private function matchesPrice(?float $price): bool
    {
        if (null === $this->priceMin && null === $this->priceMax) {
            return true;
        }

        return null !== $price
            && (null === $this->priceMin || $price >= $this->priceMin)
            && (null === $this->priceMax || $price <= $this->priceMax);
    }

    /** @param list<string> $expected */
    private static function oneOf(array $expected, ?string $actual): bool
    {
        if ([] === $expected) {
            return true;
        }
        if (null === $actual) {
            return false;
        }

        $actual = mb_strtolower(trim($actual));
        foreach ($expected as $value) {
            if (mb_strtolower($value) === $actual) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trimmed, non blank values, each one once (case aside, the first spelling kept).
     *
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function texts(array $values): array
    {
        $texts = [];
        foreach ($values as $value) {
            $value = trim($value);
            if ('' !== $value) {
                $texts[mb_strtolower($value)] ??= $value;
            }
        }

        return array_values($texts);
    }

    private static function price(string $field, string $label, ?string $value): ?float
    {
        $value = null === $value ? '' : trim($value);
        if ('' === $value) {
            return null;
        }

        $number = str_replace([',', ' ', "\u{a0}", "\u{202f}"], ['.', '', '', ''], $value);
        if (!is_numeric($number) || (float) $number < 0) {
            throw new InvalidCatalogFilter($field, sprintf('%s invalide : « %s » (montant positif en euros attendu).', $label, $value));
        }

        return (float) $number;
    }

    private static function euros(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', "\u{202f}"), '0'), ',');
    }
}
