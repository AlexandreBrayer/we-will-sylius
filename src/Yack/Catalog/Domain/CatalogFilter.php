<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Filters of a catalogue listing: brands, ranges, power bands and refrigerants. Each facet holds
 * any number of values: a product matches a facet when it has one of its values, and the filter
 * when it matches every facet that has values. Built from what the customer chose: a value that
 * cannot be applied is refused here, before any query.
 */
final readonly class CatalogFilter
{
    public const FACET_BRAND = 'brand';
    public const FACET_RANGE = 'range';
    public const FACET_POWER = 'power';
    public const FACET_REFRIGERANT = 'refrigerant';

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
     */
    private function __construct(
        public array $brands = [],
        public array $ranges = [],
        public array $powerBands = [],
        public array $refrigerants = [],
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * Raw values chosen by the customer, per facet; blank values are ignored, the same value
     * twice (case aside) counts once.
     *
     * @param list<string> $brands
     * @param list<string> $ranges
     * @param list<string> $powerBands codes of POWER_BANDS
     * @param list<string> $refrigerants
     *
     * @throws InvalidCatalogFilter unknown power band
     */
    public static function fromInput(array $brands = [], array $ranges = [], array $powerBands = [], array $refrigerants = []): self
    {
        $bands = self::texts($powerBands);
        foreach ($bands as $band) {
            if (!isset(self::POWER_BANDS[$band])) {
                throw new InvalidCatalogFilter(self::FACET_POWER, sprintf('Tranche de puissance inconnue : « %s » (attendu : %s).', $band, implode(', ', array_keys(self::POWER_BANDS))));
            }
        }

        return new self(self::texts($brands), self::texts($ranges), $bands, self::texts($refrigerants));
    }

    public function isEmpty(): bool
    {
        return [] === $this->brands && [] === $this->ranges && [] === $this->powerBands && [] === $this->refrigerants;
    }

    /** Same filter without the values of one facet (its own values stay countable). */
    public function without(string $facet): self
    {
        return new self(
            self::FACET_BRAND === $facet ? [] : $this->brands,
            self::FACET_RANGE === $facet ? [] : $this->ranges,
            self::FACET_POWER === $facet ? [] : $this->powerBands,
            self::FACET_REFRIGERANT === $facet ? [] : $this->refrigerants,
        );
    }

    public function matches(ProductFacetValues $product): bool
    {
        return self::oneOf($this->brands, $product->brand)
            && self::oneOf($this->ranges, $product->range)
            && self::oneOf($this->refrigerants, $product->refrigerant)
            && ([] === $this->powerBands || (null !== $product->powerKw && \in_array(self::powerBandOf($product->powerKw), $this->powerBands, true)));
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
}
