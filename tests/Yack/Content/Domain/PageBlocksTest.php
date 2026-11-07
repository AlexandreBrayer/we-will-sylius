<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Domain\InvalidPageBlocks;
use App\Yack\Content\Domain\PageBlocks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        yield 'sixth tile' => [[['type' => 'tuiles', 'props' => ['label6' => 'En trop']]], 'propriété(s) non autorisée(s) : label6'];
        yield 'too many blocks' => [array_fill(0, PageBlocks::MAX_BLOCKS + 1, ['type' => 'faq', 'props' => []]), 'Une page contient au plus 30 blocs.'];
    }
}
