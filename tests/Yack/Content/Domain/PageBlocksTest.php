<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Entity\Content\CmsMedia;
use App\Tests\Yack\Content\Application\CmsMediaImages;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Domain\BlockLink;
use App\Yack\Content\Domain\BlockPath;
use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Domain\InvalidPageBlocks;
use App\Yack\Content\Domain\PageBlocks;
use App\Yack\Content\Domain\StyleDeviation;
use App\Yack\Content\Domain\StyleNotAllowed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[CoversClass(PageBlocks::class)]
final class PageBlocksTest extends TestCase
{
    public function testNormalizeFillsMissingPropsWithTheirDefault(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'cta', 'props' => ['title' => 'Vous êtes installateur ?']]]);

        $this->assertSame('cta', $blocks[0]['type']);
        $this->assertSame('Vous êtes installateur ?', $blocks[0]['props']['title']);
        $this->assertSame('magenta', $blocks[0]['props']['theme']);
        $this->assertSame('tous', $blocks[0]['props']['audience']);
    }

    public function testOnlyFreeBlocksAcceptAnAudience(): void
    {
        foreach (BlockType::cases() as $type) {
            $expected = null === $type->scope() ? BlockType::AUDIENCES : null;
            $this->assertSame($expected, $type->props()['audience'] ?? null, $type->label());
        }
    }

    public function testKeyFiguresBlockExposesFourSlots(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'chiffres-cles', 'props' => ['value1' => '48 h', 'label1' => 'Livraison']]]);

        $this->assertSame('48 h', $blocks[0]['props']['value1']);
        $this->assertSame('', $blocks[0]['props']['value4']);
        $this->assertSame('clair', $blocks[0]['props']['theme']);
    }

    public function testQuickAccessBlockOnlyAcceptsKnownPictos(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'acces-rapides', 'props' => ['icon1' => 'panier', 'label1' => 'Commander']]]);

        $this->assertSame('panier', $blocks[0]['props']['icon1']);
        $this->assertSame('livraison', $blocks[0]['props']['icon2']);
    }

    public function testQuickAccessTilesHaveATextAndCanBeHighlighted(): void
    {
        $blocks = PageBlocks::normalize([[
            'type' => 'acces-rapides',
            'props' => ['icon2' => 'compte', 'label2' => 'Trouver un installateur', 'text2' => 'Un réseau de pros près de chez vos clients.', 'highlight2' => 'oui'],
        ]]);

        $this->assertSame('compte', $blocks[0]['props']['icon2']);
        $this->assertSame('Un réseau de pros près de chez vos clients.', $blocks[0]['props']['text2']);
        $this->assertSame('oui', $blocks[0]['props']['highlight2']);
        $this->assertSame('non', $blocks[0]['props']['highlight1']);
        $this->assertSame('', $blocks[0]['props']['text4']);
    }

    public function testHeroAcceptsAKickerASecondButtonAndTheProfileCard(): void
    {
        $blocks = PageBlocks::normalize([[
            'type' => 'hero',
            'props' => ['kicker' => 'Click & Yack', 'cta2Label' => 'Trouver une pièce', 'cta2Url' => '/sav', 'card' => 'profil'],
        ]]);

        $this->assertSame('Click & Yack', $blocks[0]['props']['kicker']);
        $this->assertSame('Trouver une pièce', $blocks[0]['props']['cta2Label']);
        $this->assertSame('/sav', $blocks[0]['props']['cta2Url']);
        $this->assertSame('profil', $blocks[0]['props']['card']);
    }

    public function testExistingHeroKeepsNoCardAndNoSecondButton(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'hero', 'props' => ['title' => 'Le génie climatique, en un clic.']]]);

        $this->assertSame('', $blocks[0]['props']['kicker']);
        $this->assertSame('', $blocks[0]['props']['cta2Label']);
        $this->assertSame('', $blocks[0]['props']['cta2Url']);
        $this->assertSame('non', $blocks[0]['props']['card']);
    }

    public function testHeroAcceptsAPhotoBackground(): void
    {
        $blocks = PageBlocks::normalize([[
            'type' => 'hero',
            'props' => ['theme' => 'photo', 'image' => 'yack/cms/hero.jpg', 'card' => 'profil'],
        ]]);

        $this->assertSame('photo', $blocks[0]['props']['theme']);
        $this->assertSame('yack/cms/hero.jpg', $blocks[0]['props']['image']);
    }

    public function testExistingHeroKeepsTheMagentaBackground(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'hero', 'props' => ['image' => '']]]);

        $this->assertSame('magenta', $blocks[0]['props']['theme']);
    }

    #[DataProvider('provideMaquetteVisuals')]
    public function testMaquetteVisualsAreAllowedInTiles(string $image): void
    {
        $blocks = PageBlocks::normalize([['type' => 'tuiles', 'props' => ['label1' => 'Pompe à chaleur air/air', 'image1' => $image]]]);

        $this->assertSame($image, $blocks[0]['props']['image1']);
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function provideMaquetteVisuals(): \Iterator
    {
        yield 'hero photo' => ['yack/cms/hero.jpg'];
        yield 'solution photo' => ['yack/cms/sol-pac-air-air.jpg'];
        yield 'sector photo' => ['yack/cms/sect-tertiaire.jpg'];
        yield 'illustration' => ['yack/cms/illu-smart.png'];
        yield 'brand logo' => ['yack/cms/marque-rhoss.png'];
        yield 'Yack logo' => ['yack/logo-yack.png'];
    }

    public function testSupportBlockExposesThreeCards(): void
    {
        $blocks = PageBlocks::normalize([[
            'type' => 'accompagnement',
            'props' => ['title' => 'Pour vous accompagner', 'heading3' => 'Yack Technical Institute', 'image3' => 'yack/cms/illu-institute.png', 'url3' => 'page:yack-technical-institute'],
        ]]);

        $this->assertSame('Pour vous accompagner', $blocks[0]['props']['title']);
        $this->assertSame('', $blocks[0]['props']['heading1']);
        $this->assertSame('Yack Technical Institute', $blocks[0]['props']['heading3']);
        $this->assertSame('yack/cms/illu-institute.png', $blocks[0]['props']['image3']);
        $this->assertSame('page:yack-technical-institute', $blocks[0]['props']['url3']);
        $this->assertSame('tous', $blocks[0]['props']['audience']);
    }

    public function testTilesBlockExposesALayoutAndFiveSlots(): void
    {
        $blocks = PageBlocks::normalize([[
            'type' => 'tuiles',
            'props' => ['title' => 'Nos solutions', 'label5' => 'Eau chaude sanitaire', 'image5' => 'yack/cms/ballon-thermo.png', 'url5' => '/produits/eau-chaude-sanitaire'],
        ]]);

        $this->assertSame('grande-quatre', $blocks[0]['props']['layout']);
        $this->assertSame('', $blocks[0]['props']['label1']);
        $this->assertSame('Eau chaude sanitaire', $blocks[0]['props']['label5']);
        $this->assertSame('yack/cms/ballon-thermo.png', $blocks[0]['props']['image5']);
        $this->assertSame('/produits/eau-chaude-sanitaire', $blocks[0]['props']['url5']);
        $this->assertSame('tous', $blocks[0]['props']['audience']);
    }

    public function testTilesBlockIsAllowedInTemplates(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'produit-achat', 'props' => []],
            ['type' => 'tuiles', 'props' => ['layout' => 'grille', 'label1' => 'Résidentiel', 'image1' => '']],
        ], CmsTemplateType::Product);

        $this->assertSame('grille', $blocks[1]['props']['layout']);
        $this->assertSame('', $blocks[1]['props']['image1']);
    }

    #[DataProvider('provideCtaThemes')]
    public function testCtaBannerAcceptsAKickerAndTheCharteThemes(string $theme): void
    {
        $blocks = PageBlocks::normalize([['type' => 'cta', 'props' => ['kicker' => 'Réglementation F-Gas', 'theme' => $theme]]]);

        $this->assertSame('Réglementation F-Gas', $blocks[0]['props']['kicker']);
        $this->assertSame($theme, $blocks[0]['props']['theme']);
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function provideCtaThemes(): \Iterator
    {
        yield 'magenta' => ['magenta'];
        yield 'sombre' => ['sombre'];
        yield 'turquoise' => ['turquoise'];
        yield 'prune' => ['prune'];
        yield 'jaune' => ['jaune'];
    }

    public function testExistingCtaBannerKeepsMagentaAndNoKicker(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'cta', 'props' => ['title' => 'Une question sur un chantier ?']]]);

        $this->assertSame('magenta', $blocks[0]['props']['theme']);
        $this->assertSame('', $blocks[0]['props']['kicker']);
    }

    #[DataProvider('provideInvalidBlocks')]
    public function testNormalizeRefusesContentOutsideTheCharte(mixed $blocks, string $expectedMessage): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage($expectedMessage);

        PageBlocks::normalize($blocks);
    }

    /**
     * @return \Iterator<string, array{mixed, string}>
     */
    public static function provideInvalidBlocks(): \Iterator
    {
        yield 'not a list' => [['hero' => []], 'Le contenu doit être une liste de blocs.'];
        yield 'unknown block' => [[['type' => 'html', 'props' => []]], 'type « html » inconnu'];
        yield 'unknown prop' => [[['type' => 'hero', 'props' => ['style' => 'color:red']]], 'propriété(s) non autorisée(s) : style'];
        yield 'free colour' => [[['type' => 'hero', 'props' => ['theme' => '#ff0000']]], '« #ff0000 » n\'est pas une valeur autorisée pour « theme »'];
        yield 'unknown audience' => [[['type' => 'faq', 'props' => ['audience' => 'admin']]], '« admin » n\'est pas une valeur autorisée pour « audience »'];
        yield 'unknown picto' => [[['type' => 'acces-rapides', 'props' => ['icon1' => 'fusee']]], '« fusee » n\'est pas une valeur autorisée pour « icon1 »'];
        yield 'unknown quick access highlight' => [[['type' => 'acces-rapides', 'props' => ['highlight3' => 'jaune']]], '« jaune » n\'est pas une valeur autorisée pour « highlight3 »'];
        yield 'cta theme outside the charte' => [[['type' => 'cta', 'props' => ['theme' => 'clair']]], '« clair » n\'est pas une valeur autorisée pour « theme »'];
        yield 'photo hero without visual' => [[['type' => 'hero', 'props' => ['theme' => 'photo', 'image' => '']]], 'le fond « photo » demande un visuel'];
        yield 'hero visual outside the list' => [[['type' => 'hero', 'props' => ['theme' => 'photo', 'image' => 'yack/cms/autre.jpg']]], '« yack/cms/autre.jpg » n\'est pas une valeur autorisée pour « image »'];
        yield 'unknown hero card' => [[['type' => 'hero', 'props' => ['card' => 'autre']]], '« autre » n\'est pas une valeur autorisée pour « card »'];
        yield 'unknown tiles layout' => [[['type' => 'tuiles', 'props' => ['layout' => 'mosaique']]], '« mosaique » n\'est pas une valeur autorisée pour « layout »'];
        yield 'tile image outside the list' => [[['type' => 'tuiles', 'props' => ['image2' => 'https://evil.example/x.png']]], '« https://evil.example/x.png » n\'est pas une valeur autorisée pour « image2 »'];
        yield 'fourth support card' => [[['type' => 'accompagnement', 'props' => ['heading4' => 'En trop']]], 'propriété(s) non autorisée(s) : heading4'];
        yield 'support visual outside the list' => [[['type' => 'accompagnement', 'props' => ['image1' => '/etc/passwd']]], '« /etc/passwd » n\'est pas une valeur autorisée pour « image1 »'];
        yield 'sixth tile' => [[['type' => 'tuiles', 'props' => ['label6' => 'En trop']]], 'propriété(s) non autorisée(s) : label6'];
        yield 'too many blocks' => [array_fill(0, PageBlocks::MAX_BLOCKS + 1, ['type' => 'faq', 'props' => []]), 'Une page contient au plus 30 blocs (sections, colonnes et leurs blocs compris).'];
    }

    public function testCharteStyleIsKeptAtTheCharteLevel(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'faq', 'props' => []],
            ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg'], 'style' => ['fond' => 'teal']],
        ], null, BuilderLevel::Charte);

        $this->assertSame(['fond' => 'teal'], $blocks[1]['style']);
    }

    public function testFreeColourIsRefusedAtTheCharteLevel(): void
    {
        $this->expectException(StyleNotAllowed::class);
        $this->expectExceptionMessage('Bloc n°2 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.');

        PageBlocks::normalize(self::pageWithBackground('#ff7700'), null, BuilderLevel::Charte);
    }

    public function testFreeColourIsAcceptedAtTheFreeLevels(): void
    {
        foreach ([BuilderLevel::LibreAvertissement, BuilderLevel::Libre] as $level) {
            $this->assertSame(['fond' => '#ff7700'], PageBlocks::normalize(self::pageWithBackground('#ff7700'), null, $level)[1]['style'], $level->value);
        }
    }

    public function testDefaultLevelIsLibreForFixturesAndCommands(): void
    {
        $this->assertSame(['fond' => '#ff7700'], PageBlocks::normalize(self::pageWithBackground('#ff7700'))[1]['style']);
    }

    #[DataProvider('provideMalformedStyles')]
    public function testMalformedStyleIsRefusedWhateverTheLevel(mixed $style, string $message): void
    {
        foreach (BuilderLevel::cases() as $level) {
            try {
                PageBlocks::normalize([['type' => 'faq', 'props' => []], ['type' => 'faq', 'props' => [], 'style' => $style]], null, $level);
                $this->fail(sprintf('Style accepté en niveau %s.', $level->value));
            } catch (InvalidPageBlocks $exception) {
                $this->assertStringContainsString($message, $exception->getMessage(), $level->value);
            }
        }
    }

    /**
     * @return \Iterator<string, array{mixed, string}>
     */
    public static function provideMalformedStyles(): \Iterator
    {
        yield 'title size out of bounds' => [['titre' => '200px'], 'Bloc n°2 : « 200px » n\'est pas une taille de titre valide'];
        yield 'colour name' => [['fond' => 'red'], 'Bloc n°2 : « red » n\'est pas une couleur valide pour « Fond »'];
        yield 'unknown property' => [['position' => 'absolute'], 'Bloc n°2 : réglage(s) de style non autorisé(s) : position.'];
        yield 'css text' => ['background:red', 'Bloc n°2 : style invalide.'];
    }

    public function testBlockWithoutStyleComesOutWithAnEmptyStyle(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'faq', 'props' => []], ['type' => 'cta', 'props' => [], 'style' => []]], null, BuilderLevel::Charte);

        $this->assertSame([], $blocks[0]['style']);
        $this->assertSame([], $blocks[1]['style']);
        $this->assertSame(['type', 'props', 'style'], array_keys($blocks[0]));
    }

    public function testBoundBlocksAcceptAStyle(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'produit-achat', 'props' => [], 'style' => ['hautEspace' => 'l', 'fond' => 'light']]], CmsTemplateType::Product, BuilderLevel::Charte);

        $this->assertSame(['fond' => 'light', 'hautEspace' => 'l'], $blocks[0]['style']);
    }

    public function testExistingCategoryTemplatesKeepTheirLayout(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'categorie-entete', 'props' => []],
            ['type' => 'categorie-sous-categories', 'props' => []],
            ['type' => 'categorie-produits', 'props' => []],
        ], CmsTemplateType::Taxon);

        $this->assertSame('liste', $blocks[0]['props']['cadrage']);
        $this->assertSame('', $blocks[1]['props']['allLabel']);
        $this->assertSame('oui', $blocks[2]['props']['menu']);
    }

    public function testCategoryFamiliesTakeTheWholeWidthWithoutTheSideMenu(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'categorie-entete', 'props' => ['cadrage' => 'page']],
            ['type' => 'categorie-sous-categories', 'props' => ['title' => 'Familles de pièces', 'allLabel' => 'Toutes les pièces']],
            ['type' => 'categorie-produits', 'props' => ['menu' => 'non']],
        ], CmsTemplateType::Taxon, BuilderLevel::Charte);

        $this->assertSame('page', $blocks[0]['props']['cadrage']);
        $this->assertSame('Toutes les pièces', $blocks[1]['props']['allLabel']);
        $this->assertSame('non', $blocks[2]['props']['menu']);
    }

    /** @param array<string, string> $props */
    #[DataProvider('provideInvalidCategoryProps')]
    public function testCategoryBlocksRefuseUnknownLayoutValues(string $type, array $props, string $expectedMessage): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage($expectedMessage);

        PageBlocks::normalize([['type' => $type, 'props' => $props]], CmsTemplateType::Taxon);
    }

    /**
     * @return \Iterator<string, array{string, array<string, string>, string}>
     */
    public static function provideInvalidCategoryProps(): \Iterator
    {
        yield 'unknown heading frame' => ['categorie-entete', ['cadrage' => 'centre'], '« centre » n\'est pas une valeur autorisée pour « cadrage »'];
        yield 'unknown side menu value' => ['categorie-produits', ['menu' => 'peut-etre'], '« peut-etre » n\'est pas une valeur autorisée pour « menu »'];
    }

    public function testDeviationsListTheFreeValuesOfEveryBlock(): void
    {
        $blocks = [
            ['type' => 'faq', 'props' => []],
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => 'plum', 'texte' => '#123456']],
            ['type' => 'faq', 'props' => [], 'style' => ['hautEspace' => '120px']],
        ];

        $this->assertSame(
            [[2, 'texte', '#123456'], [3, 'hautEspace', '120px']],
            array_map(static fn (StyleDeviation $deviation): array => [$deviation->block, $deviation->property, $deviation->value], PageBlocks::deviations($blocks)),
        );
    }

    public function testDemoContentStaysValidAtTheCharteLevel(): void
    {
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_cms_page: array{options: array{pages: array<string, array{blocks: list<mixed>}>}}, yack_cms_template: array{options: array{templates: list<array{type: string, blocks: list<mixed>}>}}}}}}} $fixtures */
        $fixtures = Yaml::parseFile(\dirname(__DIR__, 4) . '/config/yack/fixtures_cms.yaml');
        $suite = $fixtures['sylius_fixtures']['suites']['yack']['fixtures'];

        $count = 0;
        foreach ($suite['yack_cms_page']['options']['pages'] as $slug => $page) {
            if (self::DEVIATIONS_DEMO_PAGE === $slug) {
                continue;
            }
            $count += count(PageBlocks::normalize($page['blocks'], null, BuilderLevel::Charte));
        }
        foreach ($suite['yack_cms_template']['options']['templates'] as $template) {
            $count += count(PageBlocks::normalize($template['blocks'], CmsTemplateType::from($template['type']), BuilderLevel::Charte));
        }

        $this->assertGreaterThan(0, $count);
    }

    public function testTheDeviationsDemoPageHoldsTwoFreeValuesAtTheLibreLevel(): void
    {
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_cms_page: array{options: array{pages: array<string, array{blocks: list<mixed>}>}}}}}}} $fixtures */
        $fixtures = Yaml::parseFile(\dirname(__DIR__, 4) . '/config/yack/fixtures_cms.yaml');
        $page = $fixtures['sylius_fixtures']['suites']['yack']['fixtures']['yack_cms_page']['options']['pages'][self::DEVIATIONS_DEMO_PAGE];

        $blocks = PageBlocks::normalize($page['blocks'], null, BuilderLevel::Libre);
        $deviations = array_map(static fn (StyleDeviation $deviation): array => [$deviation->block, $deviation->property, $deviation->value], PageBlocks::deviations($blocks));

        $this->assertSame([[1, 'fond', '#ff7700'], [2, 'hautEspace', '120px']], $deviations);
    }

    public function testAnImageOfTheMediaLibraryIsAcceptedInEveryImageProp(): void
    {
        $library = self::libraryWith(self::UPLOADED);
        $blocks = PageBlocks::normalize([
            ['type' => 'hero', 'props' => ['theme' => 'photo', 'image' => self::UPLOADED]],
            ['type' => 'texte-image', 'props' => ['image' => self::UPLOADED]],
            ['type' => 'tuiles', 'props' => ['image5' => self::UPLOADED]],
            ['type' => 'accompagnement', 'props' => ['image3' => self::UPLOADED]],
        ], null, BuilderLevel::Charte, $library);

        $this->assertSame([self::UPLOADED, self::UPLOADED, self::UPLOADED, self::UPLOADED], [$blocks[0]['props']['image'], $blocks[1]['props']['image'], $blocks[2]['props']['image5'], $blocks[3]['props']['image3']]);
    }

    public function testHistoricalVisualsStayAcceptedWithTheLibrary(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg']]], null, BuilderLevel::Libre, new InMemoryCmsMediaLibrary());

        $this->assertSame('yack/cms/hero.jpg', $blocks[0]['props']['image']);
    }

    public function testAnUploadedPathMissingFromTheLibraryIsRefused(): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage('Bloc n°1 (Texte + image) : « ' . self::UPLOADED . ' » n\'est pas une valeur autorisée pour « image » : ni une image de la médiathèque, ni un visuel Yack.');

        PageBlocks::normalize([['type' => 'texte-image', 'props' => ['image' => self::UPLOADED]]], null, BuilderLevel::Libre, new InMemoryCmsMediaLibrary());
    }

    public function testWithoutTheLibraryAnUploadedPathIsCheckedOnItsFormatOnly(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'texte-image', 'props' => ['image' => self::UPLOADED]]]);

        $this->assertSame(self::UPLOADED, $blocks[0]['props']['image']);
    }

    #[DataProvider('provideForeignImages')]
    public function testAnyOtherImageIsRefusedEvenWhenTheLibraryHoldsIt(string $image): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage('ni une image de la médiathèque, ni un visuel Yack.');

        PageBlocks::normalize([['type' => 'tuiles', 'props' => ['image2' => $image]]], null, BuilderLevel::Libre, self::libraryWith($image));
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function provideForeignImages(): \Iterator
    {
        yield 'external url' => ['https://evil.example/x.png'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'traversal' => ['media/yack-cms/../../index.php'];
        yield 'svg in the directory' => ['media/yack-cms/0123456789abcdef0123456789abcdef.svg'];
        yield 'javascript' => ['javascript:alert(1)'];
    }

    public function testAMandatoryImageStaysMandatory(): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage('Bloc n°1 (Texte + image) : «  » n\'est pas une valeur autorisée pour « image ».');

        PageBlocks::normalize([['type' => 'texte-image', 'props' => ['image' => '']]], null, BuilderLevel::Libre, new InMemoryCmsMediaLibrary());
    }

    public function testImagesListsTheImagesShownByTheBlocksOnce(): void
    {
        $blocks = [
            ['type' => 'hero', 'props' => ['image' => 'yack/cms/hero.jpg']],
            ['type' => 'faq', 'props' => ['title' => 'yack/cms/hero.jpg']],
            ['type' => 'tuiles', 'props' => ['image1' => self::UPLOADED, 'image2' => '', 'image3' => 'yack/cms/hero.jpg']],
            ['type' => 'bloc-disparu', 'props' => ['image' => 'yack/cms/console.png']],
            ['type' => 'accompagnement', 'props' => []],
        ];

        $this->assertSame(['yack/cms/hero.jpg', self::UPLOADED], PageBlocks::images($blocks));
        $this->assertSame([], PageBlocks::images([]));
    }

    private const UPLOADED = 'media/yack-cms/0123456789abcdef0123456789abcdef.jpg';

    #[DataProvider('provideRefusedLinks')]
    public function testEveryLinkPropRefusesATargetTheShopDoesNotRenderWhateverTheLevel(string $link): void
    {
        foreach (BlockType::cases() as $type) {
            foreach ($type->linkProps() as $name) {
                $template = $type->scope();
                $blocks = [['type' => 'cta', 'props' => ['title' => 'Premier bloc']], ['type' => $type->value, 'props' => [$name => $link]]];
                foreach (BuilderLevel::cases() as $level) {
                    try {
                        PageBlocks::normalize($blocks, $template, $level);
                        $this->fail(sprintf('%s.%s = %s accepté en niveau %s.', $type->value, $name, json_encode($link), $level->value));
                    } catch (InvalidPageBlocks $exception) {
                        $this->assertSame(sprintf('Bloc n°2 : le lien « %s » n\'est pas une adresse autorisée.', BlockLink::quote($link)), $exception->getMessage());
                    }
                }
            }
        }
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function provideRefusedLinks(): \Iterator
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript in mixed case' => ['JaVaScRiPt:alert(1)'];
        yield 'data' => ['data:text/html,x'];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'protocol relative' => ['//evil.example'];
        yield 'https without host' => ['https:///x'];
        yield 'leading space' => [' /sav'];
        yield 'leading control character' => ["\x01javascript:alert(1)"];
        yield 'character reference' => ['&#106;avascript:alert(1)'];
    }

    public function testTheMessageOfARefusedLinkIsExact(): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage('Bloc n°3 : le lien « javascript:alert(1) » n\'est pas une adresse autorisée.');

        PageBlocks::normalize([
            ['type' => 'cta', 'props' => ['ctaUrl' => '/sav']],
            ['type' => 'tuiles', 'props' => ['url5' => 'page:nos-services']],
            ['type' => 'hero', 'props' => ['cta2Url' => 'javascript:alert(1)']],
        ], null, BuilderLevel::Libre);
    }

    public function testALinkInATemplateIsCheckedToo(): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage('Bloc n°2 : le lien « data:text/html,x » n\'est pas une adresse autorisée.');

        PageBlocks::normalize([['type' => 'produit-achat'], ['type' => 'acces-rapides', 'props' => ['url4' => 'data:text/html,x']]], CmsTemplateType::Product, BuilderLevel::Libre);
    }

    public function testAllowedLinksComeOutAsIs(): void
    {
        $links = ['page:nos-services', '/produits?marque=Yack', 'https://www.example.org/yack', 'mailto:contact@example.org', 'tel:+596 596 00 00 00'];
        $blocks = PageBlocks::normalize([
            ['type' => 'hero', 'props' => ['ctaUrl' => $links[0], 'cta2Url' => '']],
            ['type' => 'tuiles', 'props' => ['url1' => $links[1], 'url2' => $links[2], 'url3' => $links[3], 'url4' => $links[4]]],
        ], null, BuilderLevel::Charte);

        $this->assertSame([$links[0], ''], [$blocks[0]['props']['ctaUrl'], $blocks[0]['props']['cta2Url']]);
        $this->assertSame([...\array_slice($links, 1), ''], [$blocks[1]['props']['url1'], $blocks[1]['props']['url2'], $blocks[1]['props']['url3'], $blocks[1]['props']['url4'], $blocks[1]['props']['url5']]);
    }

    public function testEveryLinkOfTheFixturesIsAllowed(): void
    {
        $file = \dirname(__DIR__, 4) . '/config/yack/fixtures_cms.yaml';
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_cms_page: array{options: array{pages: array<string, array{blocks: list<mixed>}>}}, yack_cms_template: array{options: array{templates: list<array{blocks: list<mixed>}>}}}}}}} $fixtures */
        $fixtures = Yaml::parseFile($file);
        $suite = $fixtures['sylius_fixtures']['suites']['yack']['fixtures'];
        $contents = [...array_values($suite['yack_cms_page']['options']['pages']), ...$suite['yack_cms_template']['options']['templates']];

        $links = [];
        foreach ($contents as $content) {
            foreach (PageBlocks::walk($content['blocks']) as [, $block, $type]) {
                $props = is_array($block['props'] ?? null) ? $block['props'] : [];
                foreach (BlockType::from($type)->linkProps() as $name) {
                    if (is_string($props[$name] ?? null)) {
                        $links[] = $props[$name];
                    }
                }
            }
        }

        // Every link line of the file is read: same count as a plain search of the link props.
        $this->assertSame(preg_match_all('/^\s*(cta2?Url|url\d+):/m', (string) file_get_contents($file)), \count($links));
        $this->assertSame([], array_values(array_filter($links, static fn (string $link): bool => !BlockLink::isAllowed($link))));
        $this->assertNotSame([], array_filter($links, static fn (string $link): bool => '' !== $link));
    }

    #[DataProvider('provideRefusedDocumentLinks')]
    public function testEveryLineOfADocumentsBlockRefusesALinkSyliusDoesNotAllowWhateverTheLevel(string $link): void
    {
        $this->assertSame(['items'], BlockType::Documents->linkListProps());
        $items = "Catalogue | /produits\nPiège | " . $link . "\nServices | page:nos-services";
        foreach (BuilderLevel::cases() as $level) {
            try {
                PageBlocks::normalize([['type' => 'cta', 'props' => []], ['type' => 'documents', 'props' => ['items' => $items]]], null, $level);
                $this->fail(sprintf('documents.items avec %s accepté en niveau %s.', json_encode($link), $level->value));
            } catch (InvalidPageBlocks $exception) {
                $this->assertSame(sprintf('Bloc n°2 : le lien « %s » n\'est pas une adresse autorisée.', BlockLink::quote($link)), $exception->getMessage());
            }
        }
    }

    /**
     * Links refused in a « Libellé | lien » line (blanks around the link are not part of it, as on the shop).
     *
     * @return \Iterator<string, array{string}>
     */
    public static function provideRefusedDocumentLinks(): \Iterator
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript in mixed case' => ['JaVaScRiPt:alert(1)'];
        yield 'data' => ['data:text/html,x'];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'protocol relative' => ['//evil.example'];
        yield 'https without host' => ['https:///x'];
        yield 'leading control character' => ["\x01javascript:alert(1)"];
        yield 'leading zero width space' => ["\u{200B}/sav"];
        yield 'character reference' => ['&#106;avascript:alert(1)'];
        yield 'tel with letters' => ['tel:abc'];
        yield 'page with a path' => ['page:../x'];
        yield 'mailto alone' => ['mailto:'];
    }

    public function testADocumentLineLinkLeavingTheSiteThroughATabIsRefused(): void
    {
        foreach (["Doc | /\t/evil.example", "Doc | /\t\\evil.example", "Doc | /\x01/evil.example", 'Doc | javascript:alert(1)', 'Doc | data:text/html,x'] as $items) {
            try {
                PageBlocks::normalize([['type' => 'documents', 'props' => ['items' => $items]]], null, BuilderLevel::Libre);
                $this->fail(json_encode($items) . ' accepté.');
            } catch (InvalidPageBlocks $exception) {
                $this->assertStringStartsWith('Bloc n°1 : le lien « ', $exception->getMessage());
            }
        }
    }

    public function testDocumentLinesWithAnAllowedLinkOrNoLinkComeOutAsIs(): void
    {
        $items = "Catalogue | /produits\r\nSans lien\n\nFiche |  https://www.example.org/fiche.pdf \nAppel | tel:+596 596 00 00 00\n | /sav\n";
        $blocks = PageBlocks::normalize([['type' => 'documents', 'props' => ['items' => $items]]], null, BuilderLevel::Charte);

        $this->assertSame($items, $blocks[0]['props']['items']);
        // The FAQ is not a list of links: an answer is free text.
        $this->assertNotSame([], PageBlocks::normalize([['type' => 'faq', 'props' => ['items' => 'Question ? | javascript: est refusé']]], null, BuilderLevel::Charte));
    }

    public function testEveryDocumentLinkOfTheFixturesIsAllowed(): void
    {
        $file = \dirname(__DIR__, 4) . '/config/yack/fixtures_cms.yaml';
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_cms_page: array{options: array{pages: array<string, array{blocks: list<mixed>}>}}, yack_cms_template: array{options: array{templates: list<array{blocks: list<mixed>}>}}}}}}} $fixtures */
        $fixtures = Yaml::parseFile($file);
        $suite = $fixtures['sylius_fixtures']['suites']['yack']['fixtures'];
        $contents = [...array_values($suite['yack_cms_page']['options']['pages']), ...$suite['yack_cms_template']['options']['templates']];

        $links = [];
        foreach ($contents as $content) {
            foreach (PageBlocks::walk($content['blocks']) as [, $block, $type]) {
                $props = is_array($block['props'] ?? null) ? $block['props'] : [];
                foreach (BlockType::from($type)->linkListProps() as $name) {
                    array_push($links, ...array_filter(BlockLink::inLines(is_string($props[$name] ?? null) ? $props[$name] : ''), static fn (string $link): bool => '' !== $link));
                }
            }
        }

        $this->assertNotSame([], $links);
        $this->assertSame([], array_values(array_filter($links, static fn (string $link): bool => !BlockLink::isAllowed($link))));
    }

    public function testTheDeviationsDemoPageStaysValidWithItsLinks(): void
    {
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_cms_page: array{options: array{pages: array<string, array{blocks: list<mixed>}>}}}}}}} $fixtures */
        $fixtures = Yaml::parseFile(\dirname(__DIR__, 4) . '/config/yack/fixtures_cms.yaml');
        $page = $fixtures['sylius_fixtures']['suites']['yack']['fixtures']['yack_cms_page']['options']['pages'][self::DEVIATIONS_DEMO_PAGE];

        foreach ([BuilderLevel::LibreAvertissement, BuilderLevel::Libre] as $level) {
            $this->assertNotSame([], PageBlocks::normalize($page['blocks'], null, $level));
        }
    }

    #[DataProvider('provideMalformedStoredStyles')]
    public function testReadingAStoredContentNeverFailsOnAMalformedStyle(mixed $style): void
    {
        $blocks = [
            ['type' => 'cta', 'props' => [], 'style' => $style],
            ['type' => 'texte-image', 'props' => [], 'style' => ['fond' => '#ff7700']],
        ];

        $deviations = array_map(static fn (StyleDeviation $deviation): array => [$deviation->block, $deviation->property, $deviation->value], PageBlocks::deviations($blocks));

        $this->assertSame([[2, 'fond', '#ff7700']], $deviations);
    }

    #[DataProvider('provideMalformedStoredStyles')]
    public function testWritingAMalformedStyleIsRefused(mixed $style): void
    {
        foreach (BuilderLevel::cases() as $level) {
            try {
                PageBlocks::normalize([['type' => 'cta', 'props' => []], ['type' => 'cta', 'props' => [], 'style' => $style]], null, $level);
                $this->fail(sprintf('Style %s accepté en niveau %s.', json_encode($style), $level->value));
            } catch (InvalidPageBlocks $exception) {
                $this->assertStringStartsWith('Bloc n°2 : ', $exception->getMessage());
            }
        }
    }

    /**
     * @return \Iterator<string, array{mixed}>
     */
    public static function provideMalformedStoredStyles(): \Iterator
    {
        yield 'number' => [['fond' => 5]];
        yield 'float' => [['titre' => 40.5]];
        yield 'boolean' => [['masquerMobile' => true]];
        yield 'null value' => [['fond' => null]];
        yield 'nested array' => [['fond' => ['#ff7700']]];
        yield 'nested map' => [['fond' => ['valeur' => '#ff7700']]];
        yield 'style as a string' => ['fond'];
        yield 'style as a number' => [12];
        yield 'style as a list' => [['#ff7700']];
    }

    public function testReadingAStoredContentIgnoresBlocksThatAreNotBlocks(): void
    {
        $blocks = ['un texte', ['type' => 5, 'style' => ['fond' => '#ff7700']], ['props' => []], ['type' => 'cta', 'style' => ['fond' => '#123456']]];

        $deviations = array_map(static fn (StyleDeviation $deviation): array => [$deviation->block, $deviation->property, $deviation->value], PageBlocks::deviations($blocks));

        $this->assertSame([[4, 'fond', '#123456']], $deviations);
        $this->assertSame([], PageBlocks::images($blocks));
        $this->assertSame(['yack/cms/hero.jpg'], PageBlocks::images([['type' => 'tuiles', 'props' => ['image1' => 5, 'image2' => 'yack/cms/hero.jpg']], ['type' => 'hero', 'props' => 'image']]));
    }

    /** Page of the fixtures holding two free style values, for the report of the charte deviations. */
    private const DEVIATIONS_DEMO_PAGE = 'demo-ecarts-de-charte';

    private static function libraryWith(string $path): InMemoryCmsMediaLibrary
    {
        $library = new InMemoryCmsMediaLibrary();
        $library->add(new CmsMedia($path, 'photo.jpg', CmsMediaFile::read(CmsMediaImages::jpeg())));

        return $library;
    }

    /**
     * @return list<array{type: string, props: array<string, string>, style?: array<string, string>}>
     */
    private static function pageWithBackground(string $background): array
    {
        return [
            ['type' => 'faq', 'props' => []],
            ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg'], 'style' => ['fond' => $background]],
        ];
    }

    public function testAnImageWithoutSettingsIsNormalizedWithEmptySettings(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg']],
            ['type' => 'tuiles', 'props' => ['label1' => 'Air/eau']],
        ]);

        $this->assertSame(['imageAlt' => '', 'imageFit' => '', 'imageFocus' => ''], array_intersect_key($blocks[0]['props'], array_flip(['imageAlt', 'imageFit', 'imageFocus'])));
        $this->assertSame('', $blocks[1]['props']['image5Fit']);
    }

    public function testTheSettingsOfAnImageAreKept(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'hero', 'props' => ['theme' => 'photo', 'image' => 'yack/cms/hero.jpg', 'imageAlt' => 'Salon chauffé par une PAC', 'imageFit' => 'contenir', 'imageFocus' => 'haut']],
            ['type' => 'accompagnement', 'props' => ['image2' => 'yack/logo-yack.png', 'image2Alt' => 'Logo Yack', 'image2Fit' => 'couvrir', 'image2Focus' => 'bas']],
        ], null, BuilderLevel::Charte);

        $this->assertSame(['Salon chauffé par une PAC', 'contenir', 'haut'], [$blocks[0]['props']['imageAlt'], $blocks[0]['props']['imageFit'], $blocks[0]['props']['imageFocus']]);
        $this->assertSame(['Logo Yack', 'couvrir', 'bas'], [$blocks[1]['props']['image2Alt'], $blocks[1]['props']['image2Fit'], $blocks[1]['props']['image2Focus']]);
    }

    #[DataProvider('provideInvalidImageSettings')]
    public function testImageSettingsOutsideTheirChoicesAreRefused(mixed $blocks, string $expectedMessage): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage($expectedMessage);

        PageBlocks::normalize($blocks);
    }

    /**
     * @return \Iterator<string, array{mixed, string}>
     */
    public static function provideInvalidImageSettings(): \Iterator
    {
        yield 'unknown framing' => [[['type' => 'texte-image', 'props' => ['imageFit' => 'etirer']]], '« etirer » n\'est pas une valeur autorisée pour « imageFit »'];
        yield 'css as framing' => [[['type' => 'hero', 'props' => ['imageFit' => 'cover;color:red']]], '« cover;color:red » n\'est pas une valeur autorisée pour « imageFit »'];
        yield 'unknown focal point' => [[['type' => 'tuiles', 'props' => ['image3Focus' => 'gauche']]], '« gauche » n\'est pas une valeur autorisée pour « image3Focus »'];
        yield 'settings of a sixth tile' => [[['type' => 'tuiles', 'props' => ['image6Alt' => 'En trop']]], 'propriété(s) non autorisée(s) : image6Alt'];
        yield 'settings on a block without image' => [[['type' => 'faq', 'props' => ['imageAlt' => 'Aucune image']]], 'propriété(s) non autorisée(s) : imageAlt'];
        yield 'alternative text too long' => [[['type' => 'texte-image', 'props' => ['imageAlt' => str_repeat('a', PageBlocks::MAX_PROP_LENGTH + 1)]]], 'valeur invalide pour « imageAlt »'];
        yield 'alternative text longer than the one of the library' => [[['type' => 'texte-image', 'props' => ['imageAlt' => str_repeat('é', CmsMediaFile::ALT_MAX + 1)]]], 'Bloc n°1 (Texte + image) : le texte alternatif « imageAlt » fait au plus 255 caractères, sur une ligne, sans < ni >.'];
        yield 'html in the alternative text' => [[['type' => 'hero', 'props' => ['imageAlt' => 'Salon <img src=x onerror=alert(1)>']]], 'le texte alternatif « imageAlt » fait au plus'];
        yield 'closing bracket in the alternative text' => [[['type' => 'tuiles', 'props' => ['image2Alt' => '" > script']]], 'le texte alternatif « image2Alt » fait au plus'];
        yield 'alternative text on two lines' => [[['type' => 'accompagnement', 'props' => ['image3Alt' => "Deux\nlignes"]]], 'le texte alternatif « image3Alt » fait au plus'];
    }

    public function testAnAlternativeTextOfTheLengthOfTheLibraryWithQuotesAndAmpersandsIsKept(): void
    {
        $alt = 'Unité « console » & façade d\'un salon : "PAC" 3 kW';
        $blocks = PageBlocks::normalize([
            ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg', 'imageAlt' => $alt]],
            ['type' => 'tuiles', 'props' => ['image1Alt' => str_repeat('é', CmsMediaFile::ALT_MAX)]],
        ], null, BuilderLevel::Charte);

        $this->assertSame($alt, $blocks[0]['props']['imageAlt']);
        $this->assertSame(str_repeat('é', CmsMediaFile::ALT_MAX), $blocks[1]['props']['image1Alt']);
    }

    public function testASectionHoldsColumnsAndBlocksAndComesOutNormalized(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'faq', 'props' => ['title' => 'Avant']],
            ['type' => 'section', 'props' => ['audience' => 'pro'], 'style' => ['fond' => 'light', 'hautEspace' => 'l'], 'children' => [
                ['type' => 'hero', 'props' => ['title' => 'Pleine largeur dans une section']],
                ['type' => 'colonnes', 'props' => ['ratio' => '66-33', 'alignement' => 'centre'], 'columns' => [
                    [['type' => 'faq', 'props' => ['title' => 'Questions']]],
                    [['type' => 'accompagnement', 'props' => ['title' => 'Besoin d\'aide ?']], ['type' => 'reassurance', 'props' => []]],
                ]],
            ]],
            ['type' => 'colonnes', 'props' => ['ratio' => '33-33-33', 'espacement' => 'l'], 'style' => ['basEspace' => 'xl'], 'columns' => [[], [['type' => 'documents', 'props' => []]], []]],
        ], null, BuilderLevel::Charte);

        $this->assertSame(['type', 'props', 'style'], array_keys($blocks[0]));
        $this->assertSame(['type' => 'section', 'props' => ['audience' => 'pro'], 'style' => ['fond' => 'light', 'hautEspace' => 'l']], array_diff_key($blocks[1], ['children' => true]));
        $section = $blocks[1]['children'] ?? [];
        $this->assertSame(['hero', 'colonnes'], array_column($section, 'type'));
        $this->assertSame(['ratio' => '66-33', 'alignement' => 'centre', 'espacement' => 'm', 'audience' => 'tous'], $section[1]['props']);
        $this->assertSame([['faq'], ['accompagnement', 'reassurance']], array_map(static fn (array $column): array => array_column($column, 'type'), $section[1]['columns']));
        $this->assertSame(['type', 'props', 'style', 'columns'], array_keys($blocks[2]));
        $this->assertSame([0, 1, 0], array_map(count(...), $blocks[2]['columns'] ?? []));
        $this->assertSame('Questions', $section[1]['columns'][0][0]['props']['title']);
    }

    public function testEmptyContainersAreAllowedAndColumnsDefaultToTheirRatio(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'section'],
            ['type' => 'colonnes'],
            ['type' => 'colonnes', 'props' => ['ratio' => '33-33-33']],
        ]);

        $this->assertSame(['type' => 'section', 'props' => ['audience' => 'tous'], 'style' => [], 'children' => []], $blocks[0]);
        $this->assertSame(['ratio' => '50-50', 'alignement' => 'haut', 'espacement' => 'm', 'audience' => 'tous'], $blocks[1]['props']);
        $this->assertSame([[], []], $blocks[1]['columns'] ?? null);
        $this->assertSame([[], [], []], $blocks[2]['columns'] ?? null);
    }

    #[DataProvider('provideRefusedTrees')]
    public function testATreeOutsideTheLayoutRulesIsRefusedWithThePathOfTheBlock(mixed $blocks, string $message, ?CmsTemplateType $template = null): void
    {
        foreach (BuilderLevel::cases() as $level) {
            try {
                PageBlocks::normalize($blocks, $template, $level);
                $this->fail(sprintf('Contenu accepté en niveau %s.', $level->value));
            } catch (InvalidPageBlocks $exception) {
                $this->assertSame($message, $exception->getMessage(), $level->value);
            }
        }
    }

    /**
     * @return \Iterator<string, array{0: mixed, 1: string, 2?: CmsTemplateType}>
     */
    public static function provideRefusedTrees(): \Iterator
    {
        $faq = ['type' => 'faq', 'props' => []];
        $columns = static fn (array ...$columns): array => ['type' => 'colonnes', 'props' => [], 'columns' => $columns];

        yield 'section in a section (third level)' => [[['type' => 'section', 'children' => [['type' => 'section', 'children' => [$faq]]]]], 'Section 1 › section 1 : une section se place à la racine de la page, jamais dans une section ou une colonne.'];
        yield 'columns in a column (third level)' => [[['type' => 'section', 'children' => [$columns([$columns([], [])], [])]]], 'Section 1 › colonnes 1 › colonne 1 › colonnes 1 : une colonne ne contient que des blocs de contenu, ni section ni colonnes.'];
        yield 'section in a column' => [[$columns([$faq], [$faq, ['type' => 'section']])], 'Colonnes 1 › colonne 2 › section 2 : une section se place à la racine de la page, jamais dans une section ou une colonne.'];
        yield 'hero in a column' => [[['type' => 'section', 'children' => [$faq, $columns([['type' => 'hero', 'props' => []]], [])]]], 'Section 1 › colonnes 2 › colonne 1 › bloc 1 (Bandeau principal) : bloc pleine largeur, interdit dans une colonne.'];
        yield 'products in a column' => [[$columns([], [['type' => 'produits']])], 'Colonnes 1 › colonne 2 › bloc 1 (Produits mis en avant) : bloc pleine largeur, interdit dans une colonne.'];
        yield 'four columns' => [[$columns([], [], [], [])], 'Colonnes 1 : la répartition « 50-50 » demande 2 colonnes, 4 reçues.'];
        yield 'one column for three' => [[['type' => 'colonnes', 'props' => ['ratio' => '33-33-33'], 'columns' => [[$faq]]]], 'Colonnes 1 : la répartition « 33-33-33 » demande 3 colonnes, 1 reçue.'];
        yield 'ratio outside the list' => [[['type' => 'colonnes', 'props' => ['ratio' => '25-75']]], 'Colonnes 1 : « 25-75 » n\'est pas une valeur autorisée pour « ratio ».'];
        yield 'gap outside the charte' => [[['type' => 'colonnes', 'props' => ['espacement' => '13px']]], 'Colonnes 1 : « 13px » n\'est pas une valeur autorisée pour « espacement ».'];
        yield 'columns as a map' => [[['type' => 'colonnes', 'columns' => ['gauche' => [], 'droite' => []]]], 'Colonnes 1 : les colonnes doivent être une liste de colonnes.'];
        yield 'column that is not a list' => [[$faq, ['type' => 'colonnes', 'columns' => ['texte', []]]], 'Colonnes 2 › colonne 1 : le contenu doit être une liste de blocs.'];
        yield 'children that are not a list' => [[['type' => 'section', 'children' => 'faq']], 'Section 1 : le contenu doit être une liste de blocs.'];
        yield 'children of a content block' => [[['type' => 'faq', 'children' => [$faq]]], 'Bloc n°1 (FAQ) : un bloc de contenu ne contient pas d\'autres blocs.'];
        yield 'columns of a section' => [[['type' => 'section', 'columns' => [[], []]]], 'Section 1 : une section range ses blocs dans « children », des colonnes dans « columns ».'];
        yield 'children of a columns block' => [[['type' => 'colonnes', 'children' => [$faq]]], 'Colonnes 1 : une section range ses blocs dans « children », des colonnes dans « columns ».'];
        yield 'nested block without type' => [[['type' => 'section', 'children' => [$faq, ['props' => []]]]], 'Section 1 › bloc 2 : type manquant.'];
        yield 'nested unknown type' => [[$columns([], [['type' => 'carrousel']])], 'Colonnes 1 › colonne 2 › bloc 1 : type « carrousel » inconnu.'];
        yield 'nested prop not allowed' => [[['type' => 'section', 'children' => [['type' => 'faq', 'props' => ['html' => '<b>']]]]], 'Section 1 › bloc 1 (FAQ) : propriété(s) non autorisée(s) : html.'];
        yield 'nested link refused' => [[['type' => 'section', 'children' => [$columns([], [['type' => 'accompagnement', 'props' => ['url1' => 'javascript:alert(1)']]])]]], 'Section 1 › colonnes 1 › colonne 2 › bloc 1 : le lien « javascript:alert(1) » n\'est pas une adresse autorisée.'];
        yield 'title size on a container' => [[['type' => 'section', 'style' => ['titre' => 'l', 'accent' => 'teal']]], 'Section 1 : réglage(s) de style non autorisé(s) : titre, accent.'];
        yield 'nested malformed style' => [[$columns([['type' => 'faq', 'style' => ['fond' => 'red']]], [])], 'Colonnes 1 › colonne 1 › bloc 1 : « red » n\'est pas une couleur valide pour « Fond » (une couleur de la charte, ou #rrggbb).'];
        yield 'bound block in a section of a page' => [[['type' => 'section', 'children' => [['type' => 'produit-achat']]]], 'Section 1 › bloc 1 (Achat (prix, stock, panier)) : réservé aux templates produit.'];
        yield 'bound block in a section of its template' => [[['type' => 'section', 'children' => [['type' => 'produit-achat']]]], 'Section 1 › bloc 1 (Achat (prix, stock, panier)) : un bloc lié reste à la racine du template, hors des sections et des colonnes.', CmsTemplateType::Product];
        yield 'bound block in a column of its template' => [[['type' => 'produit-achat'], $columns([['type' => 'produit-description']], [])], 'Colonnes 2 › colonne 1 › bloc 1 (Description) : un bloc lié reste à la racine du template, hors des sections et des colonnes.', CmsTemplateType::Product];
        yield 'nested blocks counted' => [[['type' => 'section', 'children' => array_fill(0, PageBlocks::MAX_BLOCKS, $faq)]], 'Une page contient au plus 30 blocs (sections, colonnes et leurs blocs compris).'];
    }

    public function testATemplateAcceptsContainersOfFreeBlocksAroundItsBoundBlocks(): void
    {
        $blocks = PageBlocks::normalize([
            ['type' => 'produit-achat'],
            ['type' => 'section', 'children' => [['type' => 'colonnes', 'columns' => [[['type' => 'faq']], [['type' => 'documents']]]]]],
        ], CmsTemplateType::Product, BuilderLevel::Charte);

        $this->assertSame(['produit-achat', 'section'], array_column($blocks, 'type'));
    }

    public function testTheMandatoryBlockOfATemplateIsLookedForAtTheRoot(): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage('Un template produit doit contenir le bloc « Achat (prix, stock, panier) ».');

        PageBlocks::normalize([['type' => 'section', 'children' => [['type' => 'faq']]]], CmsTemplateType::Product);
    }

    #[DataProvider('provideFreeValuesInContainers')]
    public function testAFreeValueInAContainerIsRefusedAtTheCharteLevel(mixed $blocks, string $message): void
    {
        try {
            PageBlocks::normalize($blocks, null, BuilderLevel::Charte);
            $this->fail('Valeur hors charte acceptée.');
        } catch (StyleNotAllowed $refused) {
            $this->assertSame($message, $refused->getMessage());
        }
        $this->assertNotSame([], PageBlocks::normalize($blocks, null, BuilderLevel::LibreAvertissement));
    }

    /**
     * @return \Iterator<string, array{mixed, string}>
     */
    public static function provideFreeValuesInContainers(): \Iterator
    {
        yield 'background of a section' => [[['type' => 'faq'], ['type' => 'section', 'style' => ['fond' => '#ff7700']]], 'Section 2 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.'];
        yield 'spacing of a columns block' => [[['type' => 'section', 'children' => [['type' => 'colonnes', 'style' => ['hautEspace' => '120px']]]]], 'Section 1 › colonnes 1 : l\'espacement 120px est hors charte. Votre compte ne permet pas de sortir de la charte.'];
        yield 'block in a column' => [[['type' => 'colonnes', 'columns' => [[], [['type' => 'faq', 'style' => ['titre' => '50px']]]]]], 'Colonnes 1 › colonne 2 › bloc 1 : la taille de titre 50px est hors charte. Votre compte ne permet pas de sortir de la charte.'];
    }

    public function testDeviationsImagesAndCountReadTheWholeTree(): void
    {
        $blocks = [
            ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg'], 'style' => ['fond' => '#123456']],
            ['type' => 'section', 'props' => [], 'style' => ['fond' => '#ff7700'], 'children' => [
                ['type' => 'colonnes', 'props' => [], 'style' => [], 'columns' => [
                    [['type' => 'texte-image', 'props' => ['image' => self::UPLOADED], 'style' => ['titre' => '50px']]],
                    [['type' => 'accompagnement', 'props' => ['image2' => 'yack/cms/hero.jpg', 'image3' => 'yack/logo-yack.png'], 'style' => []]],
                ]],
                ['type' => 'faq', 'props' => [], 'style' => ['basEspace' => '120px']],
            ]],
        ];

        $this->assertSame([
            [1, 'Bloc n°1', 'texte-image', 'fond', '#123456'],
            [2, 'Section 2', 'section', 'fond', '#ff7700'],
            [2, 'Section 2 › colonnes 1 › colonne 1 › bloc 1', 'texte-image', 'titre', '50px'],
            [2, 'Section 2 › bloc 2', 'faq', 'basEspace', '120px'],
        ], array_map(static fn (StyleDeviation $deviation): array => [$deviation->block, $deviation->where, $deviation->type, $deviation->property, $deviation->value], PageBlocks::deviations($blocks)));
        $this->assertSame(['yack/cms/hero.jpg', self::UPLOADED, 'yack/logo-yack.png'], PageBlocks::images($blocks));
        $this->assertSame(6, PageBlocks::count($blocks));
    }

    public function testReadingAStoredTreeSkipsWhatIsNotABlockAndKeepsItsKeys(): void
    {
        $blocks = [
            'a' => ['type' => 'section', 'children' => 'faq'],
            'b' => ['type' => 'colonnes', 'columns' => ['texte', ['x' => ['type' => 'faq'], 'y' => 7]]],
            'c' => ['type' => 'section', 'children' => [null, ['type' => 'bloc-disparu']]],
        ];

        $this->assertSame(
            ['Section 1', 'Colonnes 2', 'Colonnes 2 › colonne 2 › bloc 1', 'Section 3', 'Section 3 › bloc 2'],
            array_map(static fn (array $node): string => $node[0]->label(), PageBlocks::walk($blocks)),
        );
        $marked = PageBlocks::map($blocks, static fn (array $block, BlockPath $path, string $type): array => [...$block, 'vu' => $type]);
        $this->assertSame(['type' => 'faq', 'vu' => 'faq'], $marked['b']['columns'][1]['x'] ?? null);
        $this->assertSame(7, $marked['b']['columns'][1]['y'] ?? null);
        $this->assertSame('texte', $marked['b']['columns'][0] ?? null);
        $this->assertSame('faq', $marked['a']['children'] ?? null);
        $this->assertSame(5, PageBlocks::count($blocks));
    }
}
