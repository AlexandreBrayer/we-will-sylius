<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/**
 * Règles de correspondance « référence → documents », du plus sûr au moins sûr ; un document n'apparaît
 * qu'au meilleur niveau qu'il atteint.
 *
 * - exact (`reference_identique`) : la référence figure dans les modèles ou les codes catalogue du document ;
 * - série (`variante_suffixe`) : même préfixe, puissance et série, autre suffixe ;
 * - série (`autre_puissance`) : même préfixe, série et suffixe de base, autre puissance ; ou la référence
 *   est dans la famille catalogue d'un modèle couvert (même non découpable) ;
 * - famille (`meme_type_appareil`) : seulement si aucun document exact ou série, même préfixe, types
 *   d'usage général (utilisation, télécommande, brochure), 5 documents au plus ;
 * - aucun : liste vide.
 *
 * Ordre : niveau, raison, puis type, français d'abord, officiel d'abord, année décroissante, titre.
 */
final class ManualMatcher
{
    public const FAMILY_LIMIT = 5;

    /** @var array<string, ?ModelReference> */
    private array $references = [];

    /**
     * @param list<Manual>        $manuals
     * @param list<ManualProduct> $products
     *
     * @return list<ManualMatch>
     */
    public function match(ModelReference $reference, array $manuals, array $products = []): array
    {
        $familyCodes = $this->familyCodes($reference, $products);

        $matches = [];
        foreach ($manuals as $manual) {
            $reason = $this->reason($reference, $manual, $familyCodes);
            if (null !== $reason) {
                $matches[] = new ManualMatch($manual, $reason);
            }
        }

        if ([] === $matches && $reference->structured) {
            $matches = $this->sameApplianceType($reference, $manuals);
        }

        usort($matches, self::compare(...));

        return $matches;
    }

    /** @param list<ManualMatch> $matches */
    public static function levelOf(array $matches): MatchLevel
    {
        $best = MatchLevel::None;
        foreach ($matches as $match) {
            if ($match->level->rank() < $best->rank()) {
                $best = $match->level;
            }
        }

        return $best;
    }

    /** Ordre d'affichage de deux documents d'un même groupe. */
    public static function compareManuals(Manual $a, Manual $b): int
    {
        return [$a->type->rank(), !$a->isInFrench(), !$a->official, -(int) ($a->year ?? 0), mb_strtolower($a->title), $a->id]
            <=> [$b->type->rank(), !$b->isInFrench(), !$b->official, -(int) ($b->year ?? 0), mb_strtolower($b->title), $b->id];
    }

    private static function compare(ManualMatch $a, ManualMatch $b): int
    {
        return [$a->level->rank(), $a->reason->rank()] <=> [$b->level->rank(), $b->reason->rank()]
            ?: self::compareManuals($a->manual, $b->manual);
    }

    /**
     * Codes des familles catalogue qui contiennent la référence (vide si aucune).
     *
     * @param list<ManualProduct> $products
     *
     * @return array<string, true>
     */
    private function familyCodes(ModelReference $reference, array $products): array
    {
        $codes = [];
        foreach ($products as $product) {
            $family = array_map($this->canonical(...), $product->codes());
            if (\in_array($reference->canonical, $family, true)) {
                foreach ($family as $code) {
                    $codes[$code] = true;
                }
            }
        }
        unset($codes[$reference->canonical]);

        return $codes;
    }

    /** @param array<string, true> $familyCodes */
    private function reason(ModelReference $reference, Manual $manual, array $familyCodes): ?MatchReason
    {
        $covered = $this->covered($manual);
        if (isset($covered[$reference->canonical])) {
            return MatchReason::IdenticalReference;
        }

        $models = $this->models($manual);
        foreach ($models as $model) {
            if ($reference->sameModelAs($model)) {
                return MatchReason::SuffixVariant;
            }
        }
        foreach ($models as $model) {
            if ($reference->sameSeriesAs($model)) {
                return MatchReason::OtherCapacity;
            }
        }

        return [] === array_intersect_key($familyCodes, $covered) ? null : MatchReason::OtherCapacity;
    }

    /**
     * @param list<Manual> $manuals
     *
     * @return list<ManualMatch>
     */
    private function sameApplianceType(ModelReference $reference, array $manuals): array
    {
        $kept = [];
        foreach ($manuals as $manual) {
            if (!$manual->type->isGeneral()) {
                continue;
            }
            foreach ($this->models($manual) as $model) {
                if ($reference->sameTypeAs($model)) {
                    $kept[] = $manual;
                    break;
                }
            }
        }
        usort($kept, self::compareManuals(...));

        return array_map(
            static fn (Manual $manual): ManualMatch => new ManualMatch($manual, MatchReason::SameApplianceType),
            \array_slice($kept, 0, self::FAMILY_LIMIT),
        );
    }

    /** @return list<ModelReference> modèles et codes catalogue du document, lus comme des références */
    private function models(Manual $manual): array
    {
        return array_values(array_filter(array_map($this->reference(...), [...$manual->models, ...$manual->catalogCodes])));
    }

    /** @return array<string, true> formes canoniques des modèles et codes catalogue du document */
    private function covered(Manual $manual): array
    {
        $covered = [];
        foreach ([...$manual->models, ...$manual->catalogCodes] as $code) {
            $covered[$this->canonical($code)] = true;
        }

        return $covered;
    }

    private function canonical(string $code): string
    {
        return $this->reference($code)->canonical ?? ModelReference::normalize($code);
    }

    private function reference(string $code): ?ModelReference
    {
        if (!\array_key_exists($code, $this->references)) {
            $this->references[$code] = ModelReference::fromDocument($code);
        }

        return $this->references[$code];
    }
}
