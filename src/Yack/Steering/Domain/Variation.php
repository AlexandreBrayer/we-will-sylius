<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * Change of a figure between two periods of the same length, in percent of the previous one (CA, panier moyen).
 * No variation when the previous period is empty: a percentage of nothing means nothing.
 */
final readonly class Variation
{
    private function __construct(public float $percent)
    {
    }

    public static function between(int $current, int $previous): ?self
    {
        if ($previous <= 0) {
            return null;
        }

        return new self(round(($current - $previous) * 100 / $previous, 1));
    }

    public function isUp(): bool
    {
        return $this->percent > 0;
    }

    public function isDown(): bool
    {
        return $this->percent < 0;
    }

    /** "+12,5 %", "-3 %", "0 %" (one decimal at most, French notation). */
    public function label(): string
    {
        $rounded = round($this->percent, 1);
        $number = number_format(abs($rounded), 0.0 === fmod(abs($rounded), 1.0) ? 0 : 1, ',', "\u{202F}");
        $sign = match (true) {
            $rounded > 0 => '+',
            $rounded < 0 => '-',
            default => '',
        };

        return $sign.$number."\u{00A0}%";
    }
}
