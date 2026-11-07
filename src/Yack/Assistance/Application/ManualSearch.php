<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Application;

use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualProduct;
use App\Yack\Assistance\Domain\MatchLevel;
use App\Yack\Assistance\Domain\MatchReason;
use App\Yack\Assistance\Domain\ModelReference;
use App\Yack\Catalog\Domain\ProductReference;

/**
 * Résultat d'une recherche de manuels : référence comprise, n° de série et les produits finis du parc, produit du
 * catalogue, groupes de documents par niveau, choix de modèles quand la saisie est un nom.
 *
 * Chaque groupe dit d'où viennent ses documents (`source`) : la référence saisie, ou les modèles du n° de série.
 * Les deux coexistent seulement en cas de conflit (le n° de série et la référence ne désignent pas le même
 * appareil). Groupes triés par niveau, puis raison, puis source (n° de série d'abord) ; documents d'un groupe
 * dans l'ordre de `ManualMatcher::compareManuals`.
 */
final readonly class ManualSearch
{
    public const SOURCE_SERIAL = 'serial';
    public const SOURCE_REFERENCE = 'reference';

    public const BRAND_FROM_CATALOG = 'catalogue';

    /**
     * @param list<string>|null                                                                       $serialProductCodes produits finis de l'appareil, null s'il est absent du parc (ou sans n° de série)
     * @param list<array{level: MatchLevel, reason: MatchReason, source: string, manuals: list<Manual>}> $groups
     * @param list<ManualProduct>                                                                     $choices
     */
    public function __construct(
        public string $query,
        public ?ModelReference $reference,
        public ?string $brand,
        public ?string $brandSource,
        public ?string $serial,
        public ?array $serialProductCodes,
        public bool $conflict,
        public ?ProductReference $product,
        public array $groups,
        public array $choices = [],
    ) {
    }

    public function serialKnown(): bool
    {
        return null !== $this->serial && null !== $this->serialProductCodes;
    }

    /** Meilleur niveau des groupes, `aucun` s'il n'y en a pas. */
    public function level(): MatchLevel
    {
        $best = MatchLevel::None;
        foreach ($this->groups as $group) {
            if ($group['level']->rank() < $best->rank()) {
                $best = $group['level'];
            }
        }

        return $best;
    }

    /** Au moins un document exact ou série a un texte indexé : une question aux manuels est possible. */
    public function askable(): bool
    {
        foreach ($this->groups as $group) {
            if (!$group['level']->isReliable()) {
                continue;
            }
            foreach ($group['manuals'] as $manual) {
                if ($manual->hasText()) {
                    return true;
                }
            }
        }

        return false;
    }
}
