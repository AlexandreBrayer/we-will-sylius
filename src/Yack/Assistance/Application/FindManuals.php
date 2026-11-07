<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Application;

use App\Yack\AfterSales\Application\ApplianceModelsInterface;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualLibrary;
use App\Yack\Assistance\Domain\ManualMatch;
use App\Yack\Assistance\Domain\ManualMatcher;
use App\Yack\Assistance\Domain\ManualProduct;
use App\Yack\Assistance\Domain\MatchLevel;
use App\Yack\Assistance\Domain\MatchReason;
use App\Yack\Assistance\Domain\ModelReference;
use App\Yack\Assistance\Domain\ReferenceBrand;
use App\Yack\Catalog\Application\ProductReferenceReaderInterface;
use App\Yack\Catalog\Domain\ProductReference;

/**
 * Recherche des manuels d'un appareil.
 *
 * Saisie libre (`q`), dans cet ordre : n° de série (ou ServiceCode) connu du parc installé ; sinon référence
 * découpable ou présente dans la base ; sinon mots cherchés dans le nom et le code des produits de la base (tous
 * les mots, accents et casse ignorés), de 1 à 8 produits proposés au choix ; sinon aucun document.
 *
 * Référence et n° de série (`reference`, `serial`, après l'écran de validation) : le n° de série ne sert jamais à
 * deviner un modèle par son format. Connu du parc, il donne les produits finis de l'appareil, cherchés chacun comme
 * une référence. Si la référence saisie n'est aucun de ces produits : conflit, et les deux résultats restent
 * (groupes distingués par leur source). Inconnu : il est rendu tel quel, sans autre effet.
 *
 * `q` l'emporte sur `reference` et `serial`. Chaque champ fait 100 caractères au plus ; l'un des trois est requis.
 */
final readonly class FindManuals
{
    public const MAX_LENGTH = 100;

    public const MAX_CHOICES = 8;

    public const REQUIRED_MESSAGE = 'Saisissez une référence, un modèle ou un n° de série.';

    public const TOO_LONG_MESSAGE = 'Saisie trop longue : 100 caractères au plus.';

    public function __construct(
        private ManualLibrary $library,
        private ApplianceModelsInterface $appliances,
        private ProductReferenceReaderInterface $catalog,
    ) {
    }

    /**
     * Champs refusés et leur message (vide : la saisie est recevable).
     *
     * @return array<'q'|'reference'|'serial', string>
     */
    public static function problems(string $q, string $reference = '', string $serial = ''): array
    {
        $fields = ['q' => trim($q), 'reference' => trim($reference), 'serial' => trim($serial)];
        if ('' === implode('', $fields)) {
            return ['q' => self::REQUIRED_MESSAGE];
        }

        return array_map(
            static fn (): string => self::TOO_LONG_MESSAGE,
            array_filter($fields, static fn (string $value): bool => mb_strlen($value) > self::MAX_LENGTH),
        );
    }

    /** @throws \InvalidArgumentException saisie refusée par `problems()` */
    public function __invoke(string $q = '', string $reference = '', string $serial = ''): ManualSearch
    {
        $problems = self::problems($q, $reference, $serial);
        if ([] !== $problems) {
            throw new \InvalidArgumentException(implode(' ', $problems));
        }

        $q = trim($q);

        return '' !== $q ? $this->freeText($q) : $this->validated(trim($reference), trim($serial));
    }

    private function freeText(string $q): ManualSearch
    {
        $codes = $this->appliances->productCodes($q);
        if (null !== $codes) {
            return $this->search($q, null, $q, $codes);
        }

        $reference = ModelReference::fromInput($q);
        if (null !== $reference && ($reference->structured || $this->inLibrary($reference))) {
            return $this->search($q, $reference, null, null);
        }

        $choices = $this->choices($q);
        if ([] !== $choices && \count($choices) <= self::MAX_CHOICES) {
            return new ManualSearch($q, null, null, null, null, null, false, null, [], $choices);
        }

        // Un seul mot reste une référence (inconnue) ; une phrase n'en est pas une.
        return $this->search($q, 1 === preg_match('/\s/u', $q) ? null : $reference, null, null);
    }

    private function validated(string $reference, string $serial): ManualSearch
    {
        $codes = '' === $serial ? null : $this->appliances->productCodes($serial);

        return $this->search(
            '' !== $reference ? $reference : $serial,
            '' === $reference ? null : ModelReference::fromInput($reference),
            '' === $serial ? null : $serial,
            $codes,
        );
    }

    /** @param list<string>|null $serialCodes produits finis du parc, null si le n° est absent du parc ou non saisi */
    private function search(string $query, ?ModelReference $reference, ?string $serial, ?array $serialCodes): ManualSearch
    {
        $fromSerial = array_values(array_filter(array_map(ModelReference::fromDocument(...), $serialCodes ?? [])));
        $conflict = null !== $reference && [] !== $fromSerial
            && !\in_array($reference->canonical, array_map(static fn (ModelReference $code): string => $code->canonical, $fromSerial), true);

        $groups = [];
        if ([] !== $fromSerial) {
            $groups = $this->groups(ManualSearch::SOURCE_SERIAL, $fromSerial);
        }
        if (null !== $reference && ([] === $fromSerial || $conflict)) {
            $groups = [...$groups, ...$this->groups(ManualSearch::SOURCE_REFERENCE, [$reference])];
        }
        usort($groups, static fn (array $a, array $b): int => [$a['level']->rank(), $a['reason']->rank(), ManualSearch::SOURCE_SERIAL === $a['source'] ? 0 : 1]
            <=> [$b['level']->rank(), $b['reason']->rank(), ManualSearch::SOURCE_SERIAL === $b['source'] ? 0 : 1]);

        $product = $this->product($reference, $serialCodes ?? []);
        [$brand, $brandSource] = $this->brand($reference, $product);

        return new ManualSearch(
            $query,
            $reference,
            $brand,
            $brandSource,
            null === $serial ? null : mb_strtoupper((string) preg_replace('/\s+/u', '', $serial)),
            null === $serial ? null : $serialCodes,
            $conflict,
            $product,
            $groups,
        );
    }

    /**
     * Documents de ces références, chacun à son meilleur niveau ; le niveau famille seulement si aucune référence de
     * la source n'a de document exact ou série (5 documents au plus).
     *
     * @param list<ModelReference> $references
     *
     * @return list<array{level: MatchLevel, reason: MatchReason, source: string, manuals: list<Manual>}>
     */
    private function groups(string $source, array $references): array
    {
        $matcher = new ManualMatcher();
        $manuals = $this->library->documents();
        $products = $this->library->products();

        /** @var array<string, ManualMatch> $best */
        $best = [];
        foreach ($references as $reference) {
            foreach ($matcher->match($reference, $manuals, $products) as $match) {
                $known = $best[$match->manual->id] ?? null;
                if (null === $known || [$match->level->rank(), $match->reason->rank()] < [$known->level->rank(), $known->reason->rank()]) {
                    $best[$match->manual->id] = $match;
                }
            }
        }

        $reliable = array_filter($best, static fn (ManualMatch $match): bool => $match->level->isReliable());
        $kept = [] !== $reliable ? $reliable : $best;
        uasort($kept, static fn (ManualMatch $a, ManualMatch $b): int => ManualMatcher::compareManuals($a->manual, $b->manual));
        if ([] === $reliable) {
            $kept = \array_slice($kept, 0, ManualMatcher::FAMILY_LIMIT);
        }

        $groups = [];
        foreach ($kept as $match) {
            $key = $match->reason->value;
            $groups[$key] ??= ['level' => $match->level, 'reason' => $match->reason, 'source' => $source, 'manuals' => []];
            $groups[$key]['manuals'][] = $match->manual;
        }

        return array_values($groups);
    }

    /** La référence figure parmi les modèles, les codes catalogue ou les produits de la base. */
    private function inLibrary(ModelReference $reference): bool
    {
        foreach ($this->library->documents() as $manual) {
            foreach ([...$manual->models, ...$manual->catalogCodes] as $code) {
                if (ModelReference::normalize($code) === $reference->canonical) {
                    return true;
                }
            }
        }
        foreach ($this->library->products() as $product) {
            if (ModelReference::normalize($product->code) === $reference->canonical) {
                return true;
            }
        }

        return false;
    }

    /**
     * Produits de la base dont le nom et le code contiennent tous les mots de la saisie.
     *
     * @return list<ManualProduct>
     */
    private function choices(string $q): array
    {
        $words = preg_split('/\s+/u', self::fold($q), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $words) {
            return [];
        }

        return array_values(array_filter($this->library->products(), static function (ManualProduct $product) use ($words): bool {
            $haystack = self::fold($product->name . ' ' . $product->code);
            foreach ($words as $word) {
                if (!str_contains($haystack, $word)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Fiche du catalogue : celle de la référence saisie, sinon celle du premier produit fini du parc qui en a une.
     *
     * @param list<string> $serialCodes
     */
    private function product(?ModelReference $reference, array $serialCodes): ?ProductReference
    {
        if (null !== $reference) {
            return $this->catalog->find($reference->canonical);
        }
        foreach ($serialCodes as $code) {
            $product = $this->catalog->find($code);
            if (null !== $product) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Marque de la référence : celle du catalogue (produit de la base, sinon fiche du catalogue), sinon déduite du
     * préfixe (`ReferenceBrand`), sinon aucune.
     *
     * @return array{?string, ?string} marque, origine (`catalogue`, `deduite` ou null)
     */
    private function brand(?ModelReference $reference, ?ProductReference $product): array
    {
        if (null === $reference) {
            return [null, null];
        }
        foreach ($this->library->products() as $known) {
            if (null !== $known->brand && ModelReference::normalize($known->code) === $reference->canonical) {
                return [$known->brand, ManualSearch::BRAND_FROM_CATALOG];
            }
        }
        if (null !== $product?->brand && '' !== $product->brand) {
            return [$product->brand, ManualSearch::BRAND_FROM_CATALOG];
        }
        $deduced = ReferenceBrand::of($reference);

        return null === $deduced ? [null, null] : [$deduced->name, ReferenceBrand::SOURCE];
    }

    /** Minuscules sans accents, pour comparer des mots. */
    private static function fold(string $text): string
    {
        $decomposed = \Normalizer::normalize(mb_strtolower($text), \Normalizer::FORM_D);

        return (string) preg_replace('/\p{Mn}+/u', '', false === $decomposed ? mb_strtolower($text) : $decomposed);
    }
}
