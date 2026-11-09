<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Entity\Content\CmsMedia;
use App\Tests\Yack\Content\Application\CmsMediaImages;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
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
        yield 'too many blocks' => [array_fill(0, PageBlocks::MAX_BLOCKS + 1, ['type' => 'faq', 'props' => []]), 'Une page contient au plus 30 blocs.'];
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
}
