<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * Serial numbers are typed by technicians from a rating plate: be lenient on case, spaces and dashes.
 */
final class SerialNumberFormat
{
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[\s\-_.]+/', '', $input));
    }
}
