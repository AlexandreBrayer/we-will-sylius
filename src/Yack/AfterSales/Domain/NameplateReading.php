<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * What was read on a rating plate: a plausible serial number (normalised) and the model printed
 * next to it, or nothing usable ("unreadable"). `simulated`: produced by the demo adapter.
 */
final readonly class NameplateReading
{
    public const MODEL_MAX = 120;

    private function __construct(
        public ?string $serialNumber,
        public ?string $model,
        public bool $simulated,
    ) {
    }

    /** A serial number that does not look like one makes the reading unreadable: never guess. */
    public static function read(?string $serialNumber, ?string $model, bool $simulated): self
    {
        if (null === $serialNumber || !SerialNumberFormat::isPlausible($serialNumber)) {
            return self::unreadable($simulated, $model);
        }

        return new self(SerialNumberFormat::normalize($serialNumber), self::cleanModel($model), $simulated);
    }

    public static function unreadable(bool $simulated, ?string $model = null): self
    {
        return new self(null, self::cleanModel($model), $simulated);
    }

    public function isReadable(): bool
    {
        return null !== $this->serialNumber;
    }

    private static function cleanModel(?string $model): ?string
    {
        $model = trim((string) preg_replace('/\s+/u', ' ', (string) $model));

        return '' === $model ? null : mb_substr($model, 0, self::MODEL_MAX);
    }
}
