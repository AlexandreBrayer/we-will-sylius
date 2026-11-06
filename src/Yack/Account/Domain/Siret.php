<?php

declare(strict_types=1);

namespace App\Yack\Account\Domain;

/**
 * French company establishment number (SIRET): 14 digits, Luhn checksum.
 * La Poste establishments (SIREN 356000000) use the "sum of digits multiple of 5" rule instead.
 */
final class Siret
{
    private const LA_POSTE_SIREN = '356000000';

    public static function normalize(string $siret): string
    {
        return preg_replace('/[\s.\-]/', '', $siret) ?? $siret;
    }

    public static function isValid(string $siret): bool
    {
        $siret = self::normalize($siret);
        if (1 !== preg_match('/^\d{14}$/', $siret)) {
            return false;
        }

        if (str_starts_with($siret, self::LA_POSTE_SIREN) && '35600000000048' !== $siret) {
            return 0 === array_sum(array_map('intval', str_split($siret))) % 5;
        }

        $sum = 0;
        foreach (str_split(strrev($siret)) as $position => $digit) {
            $value = (int) $digit * (1 === $position % 2 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return 0 === $sum % 10;
    }

    /** French intra-community VAT number: FR + 2-char key + SIREN (key checked when numeric). */
    public static function isValidFrenchVatNumber(string $vatNumber, ?string $siret = null): bool
    {
        $vatNumber = strtoupper(self::normalize($vatNumber));
        if (1 !== preg_match('/^FR([0-9A-Z]{2})(\d{9})$/', $vatNumber, $matches)) {
            return false;
        }

        if (null !== $siret && '' !== $siret && !str_starts_with(self::normalize($siret), $matches[2])) {
            return false;
        }

        if (1 === preg_match('/^\d{2}$/', $matches[1])) {
            return (int) $matches[1] === (12 + 3 * ((int) $matches[2] % 97)) % 97;
        }

        return true;
    }
}
