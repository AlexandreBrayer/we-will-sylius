<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * French wording of the figures of the direction pages: amounts in euros, counts with their noun.
 */
final class Wording
{
    private const THIN_SPACE = "\u{202F}";

    private const NO_BREAK_SPACE = "\u{00A0}";

    /** "1 commande", "3 commandes", "0 commande". */
    public static function count(int $count, string $singular, string $plural): string
    {
        return $count.self::NO_BREAK_SPACE.($count > 1 ? $plural : $singular);
    }

    /** Whole euros from cents: "58 420 €". */
    public static function euros(int $cents): string
    {
        return number_format(round($cents / 100), 0, ',', self::THIN_SPACE).self::NO_BREAK_SPACE.'€';
    }

    /** Short amount for a bar or a tile: "840 €", "21,4 k€", "1,2 M€". */
    public static function shortEuros(int $cents): string
    {
        $euros = $cents / 100;
        if (abs($euros) < 10_000) {
            return self::euros($cents);
        }
        [$value, $unit] = abs($euros) < 1_000_000 ? [$euros / 1_000, 'k€'] : [$euros / 1_000_000, 'M€'];
        $rounded = round($value, 1);

        return number_format($rounded, 0.0 === fmod(abs($rounded), 1.0) ? 0 : 1, ',', self::THIN_SPACE).self::NO_BREAK_SPACE.$unit;
    }

    /** "aujourd'hui", "hier", "il y a 6 jours". */
    public static function daysAgo(\DateTimeImmutable $at, \DateTimeImmutable $now): string
    {
        $days = (int) $at->setTime(0, 0)->diff($now->setTime(0, 0))->format('%r%a');

        return match (true) {
            $days <= 0 => 'aujourd\'hui',
            1 === $days => 'hier',
            default => 'il y a '.$days.self::NO_BREAK_SPACE.'jours',
        };
    }
}
