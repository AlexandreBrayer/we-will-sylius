<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * The only blocks CMS content can contain (Yack charte: no free HTML, no free CSS).
 * Each block is a fixed component whose content is a flat map of string props.
 * Same catalog as the SvelteKit builder (front/src/lib/blocks/registry.ts).
 *
 * Free blocks (scope() null) are allowed everywhere: pages and templates. Bound blocks
 * read the current product / category of the page being rendered (no data prop, only
 * presentation props) and are allowed only in a template of their scope.
 */
enum BlockType: string
{
    case Hero = 'hero';
    case TextImage = 'texte-image';
    case FeaturedProducts = 'produits';
    case Categories = 'categories';
    case CtaBanner = 'cta';
    case Faq = 'faq';
    case Reassurance = 'reassurance';
    case KeyFigures = 'chiffres-cles';
    case QuickAccess = 'acces-rapides';
    case Documents = 'documents';

    // Product template (current product)
    case ProductGallery = 'produit-galerie';
    case ProductInfo = 'produit-infos';
    case ProductBuyBox = 'produit-achat';
    case ProductAttributes = 'produit-caracteristiques';
    case ProductDescription = 'produit-description';
    case ProductDocuments = 'produit-documents';
    case ProductSavLink = 'produit-sav';
    case RelatedProducts = 'produits-associes';

    // Category template (current taxon)
    case TaxonHeader = 'categorie-entete';
    case TaxonProductGrid = 'categorie-produits';
    case TaxonChildren = 'categorie-sous-categories';

    /** Visuals available to editors (paths relative to the Sylius public dir). */
    public const IMAGES = [
        'yack/cms/pac-monobloc.png',
        'yack/cms/pac-split.png',
        'yack/cms/split-mural.png',
        'yack/cms/ballon-thermo.png',
        'yack/cms/gainable.png',
        'yack/cms/console.png',
    ];

    public const PICTOS = ['livraison', 'garantie', 'sav', 'stock', 'paiement', 'france', 'conseil', 'eco', 'document', 'panier'];

    /**
     * Who sees a free block: it can be restricted to a customer profile (B2B: a PRO
     * banner for installers, an ECOM promotion for buyers…). The first value is the default.
     */
    public const AUDIENCES = ['tous', 'visiteur', 'pro', 'ecom', 'connectes'];

    /** Number of slots of the repeated blocks (key figures, quick access tiles). */
    public const SLOTS = 4;

    /** Sorts of the category product grid without prices (price sorts depend on the viewer). */
    public const GRID_SORTS = ['position', 'nom-asc', 'nom-desc', 'recent', 'ancien'];

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Bandeau principal',
            self::TextImage => 'Texte + image',
            self::FeaturedProducts => 'Produits mis en avant',
            self::Categories => 'Catégories phares',
            self::CtaBanner => "Bandeau d'appel à l'action",
            self::Faq => 'FAQ',
            self::Reassurance => 'Réassurance',
            self::ProductGallery => 'Galerie produit',
            self::ProductInfo => 'Informations produit',
            self::ProductBuyBox => 'Achat (prix, stock, panier)',
            self::ProductAttributes => 'Caractéristiques techniques',
            self::ProductDescription => 'Description',
            self::ProductDocuments => 'Documents',
            self::ProductSavLink => 'Lien SAV',
            self::RelatedProducts => 'Produits associés',
            self::TaxonHeader => 'En-tête de catégorie',
            self::TaxonProductGrid => 'Liste des produits',
            self::TaxonChildren => 'Sous-catégories',
            self::KeyFigures => 'Chiffres clés',
            self::QuickAccess => 'Accès rapides',
            self::Documents => 'Documents à télécharger',
        };
    }

    /** Template type the block is bound to, null for a free block. */
    public function scope(): ?CmsTemplateType
    {
        return match ($this) {
            self::ProductGallery, self::ProductInfo, self::ProductBuyBox, self::ProductAttributes,
            self::ProductDescription, self::ProductDocuments, self::ProductSavLink, self::RelatedProducts => CmsTemplateType::Product,
            self::TaxonHeader, self::TaxonProductGrid, self::TaxonChildren => CmsTemplateType::Taxon,
            default => null,
        };
    }

    /**
     * Allowed props: name => list of allowed values (first = default), or null for free text
     * (default ''). For bound blocks, an empty `title` means the block's default heading.
     *
     * @return array<string, list<string>|null>
     */
    public function props(): array
    {
        $image = [...self::IMAGES, ''];
        $cta = ['ctaLabel' => null, 'ctaUrl' => null];

        $props = match ($this) {
            self::Hero => ['title' => null, 'subtitle' => null, 'image' => $image, 'theme' => ['magenta', 'sombre', 'clair']] + $cta,
            self::TextImage => ['title' => null, 'text' => null, 'image' => self::IMAGES, 'imagePosition' => ['gauche', 'droite']] + $cta,
            self::FeaturedProducts => ['title' => null, 'subtitle' => null, 'productCodes' => null],
            self::Categories => ['title' => null, 'taxonCodes' => null],
            self::CtaBanner => ['title' => null, 'text' => null, 'theme' => ['magenta', 'sombre']] + $cta,
            self::Faq => ['title' => null, 'items' => null],
            self::Reassurance => ['title' => null] + self::reassuranceSlots(),
            self::ProductGallery, self::ProductInfo, self::ProductBuyBox => [],
            self::ProductAttributes, self::ProductDescription, self::ProductDocuments, self::TaxonChildren => ['title' => null],
            self::ProductSavLink => ['title' => null, 'text' => null],
            self::RelatedProducts => ['title' => null, 'limit' => ['4', '8']],
            self::TaxonHeader => ['description' => ['oui', 'non']],
            self::TaxonProductGrid => ['perPage' => ['9', '18', '27'], 'sort' => self::GRID_SORTS],
            self::KeyFigures => ['title' => null, 'theme' => ['clair', 'magenta', 'sombre']] + self::slots(['value' => null, 'label' => null]),
            self::QuickAccess => ['title' => null] + self::slots(['icon' => self::PICTOS, 'label' => null, 'url' => null]),
            self::Documents => ['title' => null, 'text' => null, 'items' => null],
        };

        // Free blocks only: a bound block (product buy box…) is part of the page skeleton.
        return null === $this->scope() ? $props + ['audience' => self::AUDIENCES] : $props;
    }

    /**
     * Repeated props: ['value' => null] gives value1…value4.
     *
     * @param array<string, list<string>|null> $slot
     *
     * @return array<string, list<string>|null>
     */
    private static function slots(array $slot): array
    {
        $slots = [];
        for ($i = 1; $i <= self::SLOTS; ++$i) {
            foreach ($slot as $name => $choices) {
                $slots[$name . $i] = $choices;
            }
        }

        return $slots;
    }

    /**
     * @return array<string, list<string>|null>
     */
    private static function reassuranceSlots(): array
    {
        $slots = [];
        for ($i = 1; $i <= 4; ++$i) {
            $slots['icon' . $i] = self::PICTOS;
            $slots['title' . $i] = null;
            $slots['text' . $i] = null;
        }

        return $slots;
    }
}
