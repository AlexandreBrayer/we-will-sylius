<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/** Raison d'une correspondance ; chaque raison porte son niveau. */
enum MatchReason: string
{
    case IdenticalReference = 'reference_identique';
    case SuffixVariant = 'variante_suffixe';
    case OtherCapacity = 'autre_puissance';
    case SameApplianceType = 'meme_type_appareil';

    public function level(): MatchLevel
    {
        return match ($this) {
            self::IdenticalReference => MatchLevel::Exact,
            self::SuffixVariant, self::OtherCapacity => MatchLevel::Series,
            self::SameApplianceType => MatchLevel::Family,
        };
    }

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
