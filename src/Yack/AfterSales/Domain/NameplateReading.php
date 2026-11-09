<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * What was read on a rating plate or a label: a plausible serial number (normalised), the model reference
 * printed on it (SRK25ZS-W, as read: the catalog decides whether it is one of its products), the model name,
 * the brand printed on it (MHI, YACK: never deduced from the reference), or nothing usable ("unreadable").
 * `simulated`: produced by the demo adapter.
 */
final readonly class NameplateReading
{
    public const MODEL_MAX = 120;

    public const REFERENCE_MAX = 40;

    public const BRAND_MAX = 60;

    private function __construct(
        public ?string $serialNumber,
        public ?string $model,
        public ?string $reference,
        public bool $simulated,
        public ?string $brand,
    ) {
    }

    /** A serial number that does not look like one is dropped: never guess. The brand does not make a reading readable. */
    public static function read(?string $serialNumber, ?string $model, bool $simulated, ?string $reference = null, ?string $brand = null): self
    {
        $serial = null !== $serialNumber && SerialNumberFormat::isPlausible($serialNumber) ? SerialNumberFormat::normalize($serialNumber) : null;

        return new self($serial, self::clean($model, self::MODEL_MAX), self::cleanCode($reference, self::REFERENCE_MAX), $simulated, self::cleanCode($brand, self::BRAND_MAX));
    }

    /** Nothing usable read; the model name and the brand, when printed, are kept for the person who corrects. */
    public static function unreadable(bool $simulated, ?string $model = null, ?string $brand = null): self
    {
        return new self(null, self::clean($model, self::MODEL_MAX), null, $simulated, self::cleanCode($brand, self::BRAND_MAX));
    }

    /** A serial number or a model reference was read. */
    public function isReadable(): bool
    {
        return null !== $this->serialNumber || null !== $this->reference;
    }

    /**
     * Texts that may designate a catalog product, most precise first (reference, then model name, then serial).
     *
     * @return list<string>
     */
    public function productHints(): array
    {
        return array_values(array_unique(array_filter([$this->reference, $this->model, $this->serialNumber], static fn (?string $text): bool => null !== $text)));
    }

    /** A reference or a brand longer than its limit, or without any letter or digit, is dropped (never cut). */
    private static function cleanCode(?string $code, int $max): ?string
    {
        $code = self::clean($code, \PHP_INT_MAX);

        return null === $code || mb_strlen($code) > $max || 1 !== preg_match('/[\p{L}\p{N}]/u', $code) ? null : $code;
    }

    private static function clean(?string $text, int $max): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return '' === $text ? null : mb_substr($text, 0, $max);
    }
}
