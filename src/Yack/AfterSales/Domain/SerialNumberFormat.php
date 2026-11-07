<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * Serial numbers are typed by technicians from a rating plate: be lenient on case, spaces and dashes.
 */
final class SerialNumberFormat
{
    /** A normalised serial number: letters and digits only, at least one digit (rules out words read by mistake). */
    private const PLAUSIBLE = '/^(?=[A-Z]*\d)[A-Z0-9]{6,32}$/';

    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[\s\-_.]+/', '', $input));
    }

    /** Whether something read on a plate (photo) looks like a serial number once normalised. */
    public static function isPlausible(string $input): bool
    {
        return 1 === preg_match(self::PLAUSIBLE, self::normalize($input));
    }
}
