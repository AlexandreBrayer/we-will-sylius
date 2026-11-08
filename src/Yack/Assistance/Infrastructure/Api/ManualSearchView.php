<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Yack\Assistance\Application\ManualSearch;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualProduct;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * GET /api/v2/shop/yack/assistance/manuals?q= ou ?reference=&serial= (public) : manuels officiels d'un appareil,
 * groupés par niveau de correspondance (exact, série, famille), avec la référence comprise, le n° de série et ses
 * produits finis, la fiche du catalogue, ou les modèles à choisir quand la saisie est un nom (`moreChoices` au-delà
 * de 8). 422 si les trois champs sont vides ou si l'un dépasse 100 caractères ; 404 si le module est coupé
 * (YACK_ASSISTANCE=0).
 * Contrat : docs/API_FRONT.md, section 13.
 */
#[ApiResource(
    shortName: 'YackAssistanceManuals',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/assistance/manuals',
            normalizationContext: ['groups' => ['assistance_manuals:item']],
            provider: ManualSearchProvider::class,
            name: ManualSearchProvider::OPERATION,
        ),
    ],
)]
final readonly class ManualSearchView
{
    /**
     * @param array{read: string, canonical: string, structured: bool, corrected: bool, brand: ?string, brandSource: ?string}|null $reference
     * @param array{value: string, known: bool, savUrl: ?string, productCodes: list<string>, conflict: bool}|null                 $serial
     * @param array{code: string, name: string, slug: string}|null                                                                 $product
     * @param list<array{level: string, reason: string, source: string, documents: list<array<string, mixed>>}>                    $groups
     * @param list<array{code: string, name: string}>                                                                              $choices
     */
    public function __construct(
        /** Saisie, telle que reçue (q, sinon reference, sinon serial). */
        #[Groups(['assistance_manuals:item'])]
        public string $query,
        /** Référence comprise ; null pour un n° de série seul, un choix de modèles ou une phrase. */
        #[Groups(['assistance_manuals:item'])]
        public ?array $reference,
        /** N° de série saisi ; `known` : présent dans le parc installé ; `conflict` : la référence n'est aucun de ses produits. */
        #[Groups(['assistance_manuals:item'])]
        public ?array $serial,
        /** Fiche du catalogue (/produit/{slug}). */
        #[Groups(['assistance_manuals:item'])]
        public ?array $product,
        /** Meilleur niveau des groupes : exact, serie, famille ou aucun. */
        #[Groups(['assistance_manuals:item'])]
        public string $level,
        /** Groupes du meilleur au moins bon niveau ; `source` : reference ou serial (les deux seulement en cas de conflit). */
        #[Groups(['assistance_manuals:item'])]
        public array $groups,
        /** Modèles à choisir quand la saisie est un nom (de 1 à 8, les premiers dans l'ordre de la base). */
        #[Groups(['assistance_manuals:item'])]
        public array $choices,
        /** D'autres produits correspondent au-delà des 8 choix : la saisie est à préciser. */
        #[Groups(['assistance_manuals:item'])]
        public bool $moreChoices,
        /** Au moins un document exact ou série a un texte indexé. */
        #[Groups(['assistance_manuals:item'])]
        public bool $askable,
    ) {
    }

    public static function of(ManualSearch $search): self
    {
        $reference = $search->reference;
        $product = $search->product;

        return new self(
            $search->query,
            null === $reference ? null : [
                'read' => $reference->read,
                'canonical' => $reference->canonical,
                'structured' => $reference->structured,
                'corrected' => $reference->corrected,
                'brand' => $search->brand,
                'brandSource' => $search->brandSource,
            ],
            null === $search->serial ? null : [
                'value' => $search->serial,
                'known' => $search->serialKnown(),
                'savUrl' => $search->serialKnown() ? '/sav/' . rawurlencode($search->serial) : null,
                'productCodes' => $search->serialProductCodes ?? [],
                'conflict' => $search->conflict,
            ],
            null === $product ? null : ['code' => $product->code, 'name' => $product->name, 'slug' => $product->slug],
            $search->level()->value,
            array_map(static fn (array $group): array => [
                'level' => $group['level']->value,
                'reason' => $group['reason']->value,
                'source' => $group['source'],
                'documents' => array_map(self::document(...), $group['manuals']),
            ], $search->groups),
            array_map(static fn (ManualProduct $choice): array => ['code' => $choice->code, 'name' => $choice->name], $search->choices),
            $search->moreChoices,
            $search->askable(),
        );
    }

    /** @return array<string, mixed> */
    private static function document(Manual $manual): array
    {
        return [
            'id' => $manual->id,
            'title' => $manual->title,
            'type' => $manual->type->value,
            'typeLabel' => $manual->type->label(),
            'languages' => $manual->languages,
            'url' => $manual->url,
            'official' => $manual->official,
            'manualNumber' => $manual->manualNumber,
            'year' => $manual->year,
            'pages' => $manual->pages,
            'models' => $manual->models,
            'hasText' => $manual->hasText(),
        ];
    }
}
