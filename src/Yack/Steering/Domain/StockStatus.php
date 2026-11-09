<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * Stock badge of a part: green, orange under the alert threshold, red when nothing is available.
 */
enum StockStatus: string
{
    case Ok = 'ok';
    case Low = 'sous_seuil';
    case Out = 'rupture';
    case Untracked = 'non_suivi';

    public static function of(bool $tracked, int $available, int $threshold): self
    {
        return match (true) {
            !$tracked => self::Untracked,
            $available <= 0 => self::Out,
            $available < $threshold => self::Low,
            default => self::Ok,
        };
    }

    /** Text of the badge: the quantity available, « Rupture » or « Non suivi ». */
    public function badgeText(int $available): string
    {
        return match ($this) {
            self::Out => 'Rupture',
            self::Untracked => 'Non suivi',
            default => (string) $available,
        };
    }
}
