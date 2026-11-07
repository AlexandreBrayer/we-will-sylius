<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * What was read on a rating plate or a label: a plausible serial number (normalised), the model reference
 * printed on it (SRK25ZS-W, as read: the catalog decides whether it is one of its products), the model name,
 * or nothing usable ("unreadable"). `simulated`: produced by the demo adapter.
 */
final readonly class NameplateReading
{
    public const MODEL_MAX = 120;

    public const REFERENCE_MAX = 40;

    private function __construct(
        public ?string $serialNumber,
        public ?string $model,
        public ?string $reference,
        public bool $simulated,
    ) {
    }

    /** A serial number that does not look like one is dropped: never guess. */
    public static function read(?string $serialNumber, ?string $model, bool $simulated, ?string $reference = null): self
    {
        $serial = null !== $serialNumber && SerialNumberFormat::isPlausible($serialNumber) ? SerialNumberFormat::normalize($serialNumber) : null;

        return new self($serial, self::clean($model, self::MODEL_MAX), self::cleanReference($reference), $simulated);
    }

    public static function unreadable(bool $simulated, ?string $model = null): self
    {
        return new self(null, self::clean($model, self::MODEL_MAX), null, $simulated);
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

    private static function cleanReference(?string $reference): ?string
    {
        $reference = self::clean($reference, \PHP_INT_MAX);

        return null === $reference || mb_strlen($reference) > self::REFERENCE_MAX || 1 !== preg_match('/[\p{L}\p{N}]/u', $reference) ? null : $reference;
    }

    private static function clean(?string $text, int $max): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return '' === $text ? null : mb_substr($text, 0, $max);
    }
}
