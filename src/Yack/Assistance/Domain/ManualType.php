<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/** Type d'un document ; l'ordre des cas est l'ordre d'affichage dans un groupe. */
enum ManualType: string
{
    case Installation = 'installation';
    case Utilisation = 'utilisation';
    case Technique = 'technique';
    case Service = 'service';
    case FicheTechnique = 'fiche_technique';
    case Pieces = 'pieces';
    case Telecommande = 'telecommande';
    case Brochure = 'brochure';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Installation => 'Manuel d\'installation',
            self::Utilisation => 'Manuel d\'utilisation',
            self::Technique => 'Manuel technique',
            self::Service => 'Manuel de service',
            self::FicheTechnique => 'Fiche technique',
            self::Pieces => 'Pièces détachées',
            self::Telecommande => 'Télécommande',
            self::Brochure => 'Brochure',
            self::Autre => 'Autre document',
        };
    }

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /** Types qui restent utiles pour une autre série du même type d'appareil (généralités). */
    public function isGeneral(): bool
    {
        return \in_array($this, [self::Utilisation, self::Telecommande, self::Brochure], true);
    }
}
