<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Twig;

use App\Entity\Channel\Channel;
use App\Entity\Locale\Locale;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Tests\Conception\Rules;
use App\Yack\Catalog\Infrastructure\Twig\FrontProductUrlExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @internal
 */
#[CoversClass(FrontProductUrlExtension::class)]
final class FrontProductUrlExtensionTest extends TestCase
{
    /** Product of the demo catalog (data/yack/catalog.json, 100GTCMHY2): slug written by SyliusProductCatalog. */
    private const SLUG = 'gateway-filaire-100gtcmhy2';

    public function testProductPageOfTheFront(): void
    {
        $this->assertSame('http://localhost:8090/produit/' . self::SLUG, $this->extension('http://localhost:8090')->productUrl($this->product()));
        $this->assertNull($this->extension()->hiddenReason($this->product()));
    }

    public function testTrailingSlashOfFrontUrlAndEncodedSlug(): void
    {
        $product = $this->product(slug: 'pompe à chaleur/2');

        $this->assertSame('https://boutique.example/produit/pompe%20%C3%A0%20chaleur%2F2', $this->extension('https://boutique.example/')->productUrl($product));
    }

    public function testDisabledProduct(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(enabled: false)));
        $this->assertSame('produit désactivé', $this->extension()->hiddenReason($this->product(enabled: false)));
        $this->assertSame('produit désactivé', $this->extension()->hiddenReason($this->product(enabled: false, channelCode: null)));
    }

    public function testProductOutsideTheYackChannel(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(channelCode: 'AUTRE')));
        $this->assertNull($this->extension()->productUrl($this->product(channelCode: null)));
        $this->assertSame('produit hors du canal Yack', $this->extension()->hiddenReason($this->product(channelCode: 'AUTRE')));
        $this->assertSame('produit hors du canal Yack', $this->extension()->hiddenReason($this->product(channelCode: null)));
    }

    public function testDisabledChannel(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(channelEnabled: false)));
        $this->assertSame('canal Yack désactivé', $this->extension()->hiddenReason($this->product(channelEnabled: false)));
    }

    public function testSlugInTheDefaultLocaleOfTheChannelOnly(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(slugLocale: 'en_US')));
        $this->assertSame('pas de slug en fr_FR', $this->extension()->hiddenReason($this->product(slugLocale: 'en_US')));
    }

    public function testEmptyOrMissingSlugInTheChannelLocale(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(slug: '')));
        $this->assertNull($this->extension()->productUrl($this->product(slug: null)));
        $this->assertSame('pas de slug en fr_FR', $this->extension()->hiddenReason($this->product(slug: '')));
        $this->assertSame('pas de slug en fr_FR', $this->extension()->hiddenReason($this->product(slug: null)));
    }

    public function testChannelWithoutDefaultLocale(): void
    {
        $product = $this->product();
        $channel = $product->getChannels()->first();
        $this->assertInstanceOf(Channel::class, $channel);
        $channel->setDefaultLocale(null);

        $this->assertNull($this->extension()->productUrl($product));
        $this->assertSame('canal Yack sans langue par défaut', $this->extension()->hiddenReason($product));
    }

    public function testTwigFunction(): void
    {
        $functions = $this->extension()->getFunctions();

        $this->assertCount(2, $functions);
        $this->assertSame('yack_front_product_url', $functions[0]->getName());
        $this->assertSame('yack_front_product_hidden_reason', $functions[1]->getName());
    }

    public function testBackOfficeButtonOpensTheFrontProductPageInANewTab(): void
    {
        $button = $this->button($this->product());

        $this->assertSame('http://localhost:8088/produit/' . self::SLUG, $button->attr('href'));
        $this->assertSame('_blank', $button->attr('target'));
        $this->assertSame('noopener', $button->attr('rel'));
        $this->assertSame('btn', $button->attr('class'));
        $this->assertSame('Voir en boutique', trim($button->text()));
        $this->assertNull($button->attr('aria-describedby'));
        $this->assertCount(0, $this->render($this->product())->filter('[data-test-view-in-store-reason]'));
    }

    public function testBackOfficeButtonIsDisabledWhenTheShopDoesNotShowTheProduct(): void
    {
        $button = $this->button($this->product(enabled: false));

        $this->assertNull($button->attr('href'));
        $this->assertSame('btn disabled', $button->attr('class'));
        $this->assertSame('true', $button->attr('aria-disabled'));
        $this->assertSame('Voir en boutique', trim($button->text()));
    }

    /**
     * The reason is visible text next to the button (no tooltip: the disabled button takes no focus) and describes it.
     *
     * @param array{enabled?: bool, channelCode?: ?string, channelEnabled?: bool, slug?: ?string, slugLocale?: string} $product arguments of product()
     */
    #[DataProvider('hiddenProducts')]
    public function testBackOfficeButtonSaysWhyTheShopDoesNotShowTheProduct(array $product, string $reason): void
    {
        $page = $this->render($this->product(...$product));
        $button = $page->filter('[data-test-view-in-store]');
        $text = $page->filter('[data-test-view-in-store-reason]');

        $this->assertCount(1, $text);
        $this->assertSame($reason, $text->text());
        $this->assertNull($text->attr('title'));
        $this->assertNull($button->attr('title'));
        $this->assertSame($text->attr('id'), $button->attr('aria-describedby'));
    }

    /** @return \Iterator<string, array{array{enabled?: bool, channelCode?: ?string, channelEnabled?: bool, slug?: ?string, slugLocale?: string}, string}> */
    public static function hiddenProducts(): \Iterator
    {
        yield 'produit désactivé' => [['enabled' => false], 'Absent de la boutique : produit désactivé.'];
        yield 'hors du canal' => [['channelCode' => 'AUTRE'], 'Absent de la boutique : produit hors du canal Yack.'];
        yield 'canal désactivé' => [['channelEnabled' => false], 'Absent de la boutique : canal Yack désactivé.'];
        yield 'slug dans une autre langue' => [['slugLocale' => 'en_US'], 'Absent de la boutique : pas de slug en fr_FR.'];
        yield 'slug vide' => [['slug' => ''], 'Absent de la boutique : pas de slug en fr_FR.'];
    }

    public function testBackOfficeButtonOfTheVariantPageOpensItsProduct(): void
    {
        $variant = new ProductVariant();
        $this->product()->addVariant($variant);

        $this->assertSame('http://localhost:8088/produit/' . self::SLUG, $this->button($variant, ['variant' => true])->attr('href'));
    }

    /**
     * config/packages/yack.yaml turns off the Sylius link (Twig shop, channel host without port) and puts the Yack
     * template at the same place. Read against the AdminBundle hook files: a renamed Sylius hook or hookable would
     * bring the old button back next to the Yack one.
     *
     * @param array<string, bool> $configuration
     */
    #[DataProvider('backOfficeShopLinks')]
    public function testBackOfficeHookReplacesTheSyliusShopLink(string $syliusFile, string $hook, string $syliusHookable, string $yackHookable, string $template, array $configuration): void
    {
        $sylius = Yaml::parseFile(Rules::root() . '/vendor/sylius/sylius/src/Sylius/Bundle/AdminBundle/Resources/config/app/twig_hooks/' . $syliusFile);
        $yack = Yaml::parseFile(Rules::root() . '/config/packages/yack.yaml', Yaml::PARSE_CUSTOM_TAGS);
        $this->assertIsArray($sylius);
        $this->assertIsArray($yack);
        $original = $sylius['sylius_twig_hooks']['hooks'][$hook][$syliusHookable] ?? null;
        $hookables = $yack['sylius_twig_hooks']['hooks'][$hook] ?? null;
        $this->assertIsArray($original, "Sylius n'accroche plus {$syliusHookable} à {$hook}.");
        $this->assertIsArray($hookables);

        $this->assertSame(['enabled' => false], $hookables[$syliusHookable]);
        $this->assertSame($template, $hookables[$yackHookable]['template']);
        $this->assertFileExists(Rules::root() . '/templates/' . $template);
        $this->assertSame($original['priority'], $hookables[$yackHookable]['priority']);
        $this->assertSame($configuration, $hookables[$yackHookable]['configuration'] ?? []);
    }

    /** @return \Iterator<string, array{string, string, string, string, string, array<string, bool>}> */
    public static function backOfficeShopLinks(): \Iterator
    {
        $button = 'admin/yack/product/view_in_store.html.twig';

        yield 'Modifier Produit' => ['product/update.yaml', 'sylius_admin.product.update.content.header.title_block.actions', 'view_in_store', 'yack_view_in_store', $button, []];
        yield 'Affichage produit' => ['product/show.yaml', 'sylius_admin.product.show.content.header.title_block.actions', 'view_in_store', 'yack_view_in_store', $button, []];
        yield 'Modifier la variante' => ['product_variant/update.yaml', 'sylius_admin.product_variant.update.content.header.title_block.actions', 'view_in_store', 'yack_view_in_store', $button, ['variant' => true]];
        yield 'Flèche de la barre du haut' => ['common/component/navbar.yaml', 'sylius_admin.common.component.navbar.items', 'shop_preview', 'yack_shop_preview', 'admin/yack/layout/shop_preview.html.twig', []];
    }

    private function extension(string $frontUrl = 'http://localhost:8088'): FrontProductUrlExtension
    {
        return new FrontProductUrlExtension($frontUrl, 'YACK_WEB');
    }

    private function product(
        bool $enabled = true,
        ?string $channelCode = 'YACK_WEB',
        bool $channelEnabled = true,
        ?string $slug = self::SLUG,
        string $slugLocale = 'fr_FR',
    ): Product {
        $locale = new Locale();
        $locale->setCode('fr_FR');

        $product = new Product();
        $product->setFallbackLocale($slugLocale);
        $product->setCurrentLocale($slugLocale);
        $product->setName('Gateway Filaire');
        $product->setSlug($slug);
        $product->setEnabled($enabled);
        if (null !== $channelCode) {
            $channel = new Channel();
            $channel->setCode($channelCode);
            $channel->setEnabled($channelEnabled);
            $channel->setDefaultLocale($locale);
            $product->addChannel($channel);
        }

        return $product;
    }

    /**
     * Back-office template, rendered as the twig hook renders it (Sylius helpers reduced to their output).
     *
     * @param array<string, bool> $configuration configuration of the hookable in config/packages/yack.yaml
     */
    private function button(Product|ProductVariant $resource, array $configuration = []): Crawler
    {
        $button = $this->render($resource, $configuration)->filter('[data-test-view-in-store]');
        $this->assertCount(1, $button);

        return $button;
    }

    /** @param array<string, bool> $configuration */
    private function render(Product|ProductVariant $resource, array $configuration = []): Crawler
    {
        $twig = new Environment(new FilesystemLoader(Rules::root() . '/templates'), ['strict_variables' => true]);
        $twig->addExtension($this->extension());
        $twig->addFunction(new TwigFunction('sylius_test_html_attribute', static fn (string $name): Markup => new Markup('data-test-' . $name, 'UTF-8')));
        $twig->addFunction(new TwigFunction('ux_icon', static fn (): Markup => new Markup('<svg></svg>', 'UTF-8')));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => ['sylius.ui.view_in_store' => 'Voir en boutique'][$key] ?? $key));

        $html = $twig->render('admin/yack/product/view_in_store.html.twig', ['hookable_metadata' => ['context' => ['resource' => $resource], 'configuration' => $configuration]]);

        return new Crawler($html);
    }
}
