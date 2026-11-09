<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Application;

/**
 * Ce qui est lu sur la photo d'une plaque, pour l'écran de validation (« C'est bien ça ? ») : aucune recherche n'a
 * été faite. `referenceRead` : la référence telle que lue ; `reference` : sa forme canonique (corrigée si besoin,
 * l'écran affiche « lu : …, compris : … » quand les deux diffèrent), null si rien d'exploitable n'est lu. `brand` :
 * marque lue sur la plaque, au nom du catalogue quand elle le désigne (`brandSource` : `plaque`), sinon déduite de la
 * référence (`deduite`), sinon null.
 * `simulated` : réponse de l'adapter de démonstration.
 */
final readonly class PlateReading
{
    public const BRAND_FROM_PLATE = 'plaque';

    public function __construct(
        public ?string $serialNumber,
        public ?string $referenceRead,
        public ?string $reference,
        public ?string $model,
        public ?string $brand,
        public ?string $brandSource,
        public bool $simulated,
    ) {
    }

    /** Ni n° de série ni référence : l'écran de validation s'ouvre avec ses champs vides. */
    public function isEmpty(): bool
    {
        return null === $this->serialNumber && null === $this->reference;
    }
}
