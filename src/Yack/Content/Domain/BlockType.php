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
    case Tiles = 'tuiles';
    case Support = 'accompagnement';

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
        // Visuals of the commercial maquette (Quentin's Figma, img/ds of the demo): photos, illustrations, brand logos.
        'yack/cms/hero.jpg',
        'yack/cms/sol-pac-air-air.jpg',
        'yack/cms/sol-pac-air-eau.jpg',
        'yack/cms/sol-ecs.jpg',
        'yack/cms/sol-eau-glacee.jpg',
        'yack/cms/sol-thermostat.jpg',
        'yack/cms/sect-petit-tertiaire.jpg',
        'yack/cms/sect-tertiaire.jpg',
        'yack/cms/sect-pandora.jpg',
        'yack/cms/illu-smart.png',
        'yack/cms/illu-institute.png',
        'yack/cms/marque-mhi.png',
        'yack/cms/marque-rhoss.png',
        'yack/cms/marque-york.png',
        'yack/logo-yack.png',
    ];

    public const PICTOS = ['livraison', 'garantie', 'sav', 'stock', 'paiement', 'france', 'conseil', 'eco', 'document', 'panier', 'recherche', 'livre', 'compte'];

    /** Highlighted quick access tile (charte yellow), first value = default. */
    public const HIGHLIGHTS = ['non', 'oui'];

    /**
     * Who sees a free block: it can be restricted to a customer profile (B2B: a PRO
     * banner for installers, an ECOM promotion for buyers…). The first value is the default.
     */
    public const AUDIENCES = ['tous', 'visiteur', 'pro', 'ecom', 'connectes'];

    /**
     * Side card of the hero: none, or the card of the viewer's profile (visitor: create a PRO
     * account; PRO / ECOM: greeting and shortcuts), rendered by the front from the session.
     */
    public const HERO_CARDS = ['non', 'profil'];

    /**
     * Backgrounds of the hero (first = default): `photo` = the chosen visual as a full background
     * under the maquette gradient (then an image is required, PageBlocks::normalize()).
     */
    public const HERO_THEMES = ['magenta', 'sombre', 'clair', 'photo'];

    /** Backgrounds of the call to action banner (first = default: magenta). */
    public const CTA_THEMES = ['magenta', 'sombre', 'turquoise', 'prune', 'jaune'];

    /** Number of slots of the repeated blocks (key figures, quick access tiles). */
    public const SLOTS = 4;

    /** Range tiles: one large tile and four (grande-quatre), or a row of tiles (grille). */
    public const TILE_LAYOUTS = ['grande-quatre', 'grille'];

    /** Number of slots of the range tiles block (the large tile and four). */
    public const TILE_SLOTS = 5;

    /** Number of cards of the support block (« Pour vous accompagner » of the maquette). */
    public const SUPPORT_SLOTS = 3;

    /** Sorts of the category product grid without prices (price sorts depend on the viewer). */
    public const GRID_SORTS = ['position', 'nom-asc', 'nom-desc', 'recent', 'ancien'];

    /**
     * Framing of an image in its block (prop `<image>Fit`): '' = the block's own framing, `couvrir` = the
     * image fills its frame (cropped), `contenir` = the whole image is shown.
     */
    public const IMAGE_FITS = ['', 'couvrir', 'contenir'];

    /** Focal point of a cropped image (prop `<image>Focus`): '' = the block's own (centre). */
    public const IMAGE_FOCUSES = ['', 'haut', 'centre', 'bas'];

    /**
     * Names of the link props (linkProps()); same pattern as LINK_PROP of front/src/lib/blocks/registry.ts,
     * whose props the builder edits with its link field.
     */
    public const LINK_PROP_PATTERN = '/^(cta2?Url|url\d+)$/D';

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
            self::Tiles => 'Tuiles de gammes',
            self::Support => "Cartes d'accompagnement",
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
            self::Hero => ['kicker' => null, 'title' => null, 'subtitle' => null, 'image' => $image, 'theme' => self::HERO_THEMES]
                + $cta + ['cta2Label' => null, 'cta2Url' => null, 'card' => self::HERO_CARDS],
            self::TextImage => ['title' => null, 'text' => null, 'image' => self::IMAGES, 'imagePosition' => ['gauche', 'droite']] + $cta,
            self::FeaturedProducts => ['title' => null, 'subtitle' => null, 'productCodes' => null],
            self::Categories => ['title' => null, 'taxonCodes' => null],
            self::CtaBanner => ['kicker' => null, 'title' => null, 'text' => null, 'theme' => self::CTA_THEMES] + $cta,
            self::Faq => ['title' => null, 'items' => null],
            self::Reassurance => ['title' => null] + self::reassuranceSlots(),
            self::ProductGallery, self::ProductInfo, self::ProductBuyBox => [],
            self::ProductAttributes, self::ProductDescription, self::ProductDocuments, self::TaxonChildren => ['title' => null],
            self::ProductSavLink => ['title' => null, 'text' => null],
            self::RelatedProducts => ['title' => null, 'limit' => ['4', '8']],
            self::TaxonHeader => ['description' => ['oui', 'non']],
            self::TaxonProductGrid => ['perPage' => ['9', '18', '27'], 'sort' => self::GRID_SORTS],
            self::KeyFigures => ['title' => null, 'theme' => ['clair', 'magenta', 'sombre']] + self::slots(['value' => null, 'label' => null]),
            self::QuickAccess => ['title' => null] + self::slots(['icon' => self::PICTOS, 'label' => null, 'text' => null, 'url' => null, 'highlight' => self::HIGHLIGHTS]),
            self::Documents => ['title' => null, 'text' => null, 'items' => null],
            self::Tiles => ['title' => null, 'layout' => self::TILE_LAYOUTS] + self::slots(['label' => null, 'image' => $image, 'url' => null], self::TILE_SLOTS),
            self::Support => ['title' => null] + self::slots(['heading' => null, 'text' => null, 'image' => $image, 'url' => null], self::SUPPORT_SLOTS),
        };

        $props += $this->imageSettingProps();

        // Free blocks only: a bound block (product buy box…) is part of the page skeleton.
        return null === $this->scope() ? $props + ['audience' => self::AUDIENCES] : $props;
    }

    /**
     * Image props of the block. Their choices in props() are the historical visuals (IMAGES, plus '' when
     * the image is optional); PageBlocks::normalize() also accepts an image of the media library.
     *
     * @return list<string>
     */
    public function imageProps(): array
    {
        return match ($this) {
            self::Hero, self::TextImage => ['image'],
            self::Tiles => array_keys(self::slots(['image' => null], self::TILE_SLOTS)),
            self::Support => array_keys(self::slots(['image' => null], self::SUPPORT_SLOTS)),
            default => [],
        };
    }

    /**
     * Link props of the block (LINK_PROP_PATTERN): free text in props(), restricted by PageBlocks::normalize()
     * to the targets of BlockLink; all optional ('' = no link).
     *
     * @return list<string>
     */
    public function linkProps(): array
    {
        return array_values(array_filter(
            array_keys($this->props()),
            static fn (string $name): bool => 1 === preg_match(self::LINK_PROP_PATTERN, $name),
        ));
    }

    /**
     * Settings of each image prop (imageProps()), after the block's own props: `<image>Alt` (alternative
     * text, free; '' = decorative image), `<image>Fit` (IMAGE_FITS) and `<image>Focus` (IMAGE_FOCUSES).
     * Rendered by the front as the `alt` of the image and `yack-cms-img--<value>` classes.
     *
     * @return array<string, list<string>|null>
     */
    public function imageSettingProps(): array
    {
        $settings = [];
        foreach ($this->imageProps() as $name) {
            $settings[$name . 'Alt'] = null;
            $settings[$name . 'Fit'] = self::IMAGE_FITS;
            $settings[$name . 'Focus'] = self::IMAGE_FOCUSES;
        }

        return $settings;
    }

    /**
     * Alternative text of an image of a block (`<image>Alt`): CmsMediaFile::ALT_MAX characters at most, on one
     * line, without control characters nor `<` or `>` (no HTML). The builder cleans it the same way
     * (front/src/lib/blocks/image.ts, cleanImageAlt()).
     */
    public static function isImageAlt(string $alt): bool
    {
        return mb_strlen($alt) <= CmsMediaFile::ALT_MAX && 0 === preg_match('/[<>\p{Cc}]/u', $alt);
    }

    /**
     * Alternative text props of the block (`<image>Alt` of imageSettingProps()).
     *
     * @return list<string>
     */
    public function imageAltProps(): array
    {
        return array_map(static fn (string $name): string => $name . 'Alt', $this->imageProps());
    }

    /**
     * Repeated props: ['value' => null] gives value1…value4 (value1…value{$count}).
     *
     * @param array<string, list<string>|null> $slot
     *
     * @return array<string, list<string>|null>
     */
    private static function slots(array $slot, int $count = self::SLOTS): array
    {
        $slots = [];
        for ($i = 1; $i <= $count; ++$i) {
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
