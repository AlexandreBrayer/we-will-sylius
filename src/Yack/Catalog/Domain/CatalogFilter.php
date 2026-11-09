<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Filters of a catalogue listing: brand, range, power (kW, bounds included) and refrigerant.
 * Built from what the customer typed: a value that cannot be applied is refused here, before
 * any query.
 */
final readonly class CatalogFilter
{
    public const FACET_BRAND = 'brand';
    public const FACET_RANGE = 'range';
    public const FACET_POWER = 'power';
    public const FACET_REFRIGERANT = 'refrigerant';

    public const FIELD_POWER_MIN = 'power_min';
    public const FIELD_POWER_MAX = 'power_max';

    /** Last band, without upper bound. */
    public const OPEN_POWER_BAND = '12+';

    /**
     * Power bands of the "Puissance" facet, in kW: lower bound included, upper bound excluded,
     * except the last bounded band (9 to 12 kW includes 12 kW); "Plus de 12 kW" is above 12.
     *
     * @var array<string, array{label: string, min: float, max: float|null, maxIncluded: bool}>
     */
    public const POWER_BANDS = [
        '0-3' => ['label' => '0 à 3 kW', 'min' => 0.0, 'max' => 3.0, 'maxIncluded' => false],
        '3-6' => ['label' => '3 à 6 kW', 'min' => 3.0, 'max' => 6.0, 'maxIncluded' => false],
        '6-9' => ['label' => '6 à 9 kW', 'min' => 6.0, 'max' => 9.0, 'maxIncluded' => false],
        '9-12' => ['label' => '9 à 12 kW', 'min' => 9.0, 'max' => 12.0, 'maxIncluded' => true],
        '12+' => ['label' => 'Plus de 12 kW', 'min' => 12.0, 'max' => null, 'maxIncluded' => false],
    ];

    private function __construct(
        public ?string $brand = null,
        public ?string $range = null,
        public ?float $powerMin = null,
        public ?float $powerMax = null,
        public ?string $refrigerant = null,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * Raw values typed by the customer; empty means "no filter".
     *
     * @throws InvalidCatalogFilter
     */
    public static function fromInput(
        ?string $brand = null,
        ?string $range = null,
        ?string $powerMin = null,
        ?string $powerMax = null,
        ?string $refrigerant = null,
    ): self {
        $min = self::power(self::FIELD_POWER_MIN, 'Puissance minimale', $powerMin);
        $max = self::power(self::FIELD_POWER_MAX, 'Puissance maximale', $powerMax);
        if (null !== $min && null !== $max && $min > $max) {
            throw new InvalidCatalogFilter(self::FIELD_POWER_MIN, sprintf('La puissance minimale (%s kW) dépasse la puissance maximale (%s kW).', self::kw($min), self::kw($max)));
        }

        return new self(self::text($brand), self::text($range), $min, $max, self::text($refrigerant));
    }

    public function isEmpty(): bool
    {
        return null === $this->brand && null === $this->range && null === $this->powerMin && null === $this->powerMax && null === $this->refrigerant;
    }

    /** Same filter without the criterion of one facet (its own values stay countable). */
    public function without(string $facet): self
    {
        return new self(
            self::FACET_BRAND === $facet ? null : $this->brand,
            self::FACET_RANGE === $facet ? null : $this->range,
            self::FACET_POWER === $facet ? null : $this->powerMin,
            self::FACET_POWER === $facet ? null : $this->powerMax,
            self::FACET_REFRIGERANT === $facet ? null : $this->refrigerant,
        );
    }

    public function matches(ProductFacetValues $product): bool
    {
        return self::sameText($this->brand, $product->brand)
            && self::sameText($this->range, $product->range)
            && self::sameText($this->refrigerant, $product->refrigerant)
            && $this->matchesPower($product->powerKw);
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

    private function matchesPower(?float $powerKw): bool
    {
        if (null === $this->powerMin && null === $this->powerMax) {
            return true;
        }

        return null !== $powerKw
            && (null === $this->powerMin || $powerKw >= $this->powerMin)
            && (null === $this->powerMax || $powerKw <= $this->powerMax);
    }

    private static function sameText(?string $expected, ?string $actual): bool
    {
        return null === $expected || (null !== $actual && mb_strtolower($expected) === mb_strtolower(trim($actual)));
    }

    private static function text(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }

    private static function power(string $field, string $label, ?string $value): ?float
    {
        $value = self::text($value);
        if (null === $value) {
            return null;
        }

        $number = str_replace(',', '.', $value);
        if (!is_numeric($number) || (float) $number < 0) {
            throw new InvalidCatalogFilter($field, sprintf('%s invalide : « %s » (nombre de kW positif attendu).', $label, $value));
        }

        return (float) $number;
    }

    private static function kw(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }
}
