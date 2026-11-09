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
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
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
    }

    public function testTrailingSlashOfFrontUrlAndEncodedSlug(): void
    {
        $product = $this->product(slug: 'pompe à chaleur/2');

        $this->assertSame('https://boutique.example/produit/pompe%20%C3%A0%20chaleur%2F2', $this->extension('https://boutique.example/')->productUrl($product));
    }

    public function testDisabledProduct(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(enabled: false)));
    }

    public function testProductOutsideTheYackChannel(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(channelCode: 'AUTRE')));
        $this->assertNull($this->extension()->productUrl($this->product(channelCode: null)));
    }

    public function testDisabledChannel(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(channelEnabled: false)));
    }

    public function testSlugInTheDefaultLocaleOfTheChannelOnly(): void
    {
        $this->assertNull($this->extension()->productUrl($this->product(slugLocale: 'en_US')));

        $product = $this->product(slugLocale: 'en_US');
        $product->setCurrentLocale('fr_FR');
        $product->setSlug('');
        $this->assertNull($this->extension()->productUrl($product));
    }

    public function testChannelWithoutDefaultLocale(): void
    {
        $product = $this->product();
        $channel = $product->getChannels()->first();
        $this->assertInstanceOf(Channel::class, $channel);
        $channel->setDefaultLocale(null);

        $this->assertNull($this->extension()->productUrl($product));
    }

    public function testTwigFunction(): void
    {
        $functions = $this->extension()->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('yack_front_product_url', $functions[0]->getName());
    }

    public function testBackOfficeButtonOpensTheFrontProductPageInANewTab(): void
    {
        $button = $this->button($this->product());

        $this->assertSame('http://localhost:8088/produit/' . self::SLUG, $button->attr('href'));
        $this->assertSame('_blank', $button->attr('target'));
        $this->assertSame('noopener', $button->attr('rel'));
        $this->assertSame('btn', $button->attr('class'));
        $this->assertSame('Voir en boutique', trim($button->text()));
    }

    public function testBackOfficeButtonIsDisabledWhenTheShopDoesNotShowTheProduct(): void
    {
        $button = $this->button($this->product(enabled: false));

        $this->assertNull($button->attr('href'));
        $this->assertSame('btn disabled', $button->attr('class'));
        $this->assertSame('true', $button->attr('aria-disabled'));
        $this->assertSame('Voir en boutique', trim($button->text()));
    }

    public function testBackOfficeButtonOfTheVariantPageOpensItsProduct(): void
    {
        $variant = new ProductVariant();
        $this->product()->addVariant($variant);

        $this->assertSame('http://localhost:8088/produit/' . self::SLUG, $this->button($variant, ['variant' => true])->attr('href'));
    }

    private function extension(string $frontUrl = 'http://localhost:8088'): FrontProductUrlExtension
    {
        return new FrontProductUrlExtension($frontUrl, 'YACK_WEB');
    }

    private function product(
        bool $enabled = true,
        ?string $channelCode = 'YACK_WEB',
        bool $channelEnabled = true,
        string $slug = self::SLUG,
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

    /** Back-office template, rendered as the twig hook renders it (Sylius helpers reduced to their output). */
    /** @param array<string, bool> $configuration configuration of the hookable in config/packages/yack.yaml */
    private function button(Product|ProductVariant $resource, array $configuration = []): Crawler
    {
        $twig = new Environment(new FilesystemLoader(Rules::root() . '/templates'), ['strict_variables' => true]);
        $twig->addExtension($this->extension());
        $twig->addFunction(new TwigFunction('sylius_test_html_attribute', static fn (string $name): Markup => new Markup('data-test-' . $name, 'UTF-8')));
        $twig->addFunction(new TwigFunction('ux_icon', static fn (): Markup => new Markup('<svg></svg>', 'UTF-8')));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => ['sylius.ui.view_in_store' => 'Voir en boutique'][$key] ?? $key));

        $html = $twig->render('admin/yack/product/view_in_store.html.twig', ['hookable_metadata' => ['context' => ['resource' => $resource], 'configuration' => $configuration]]);
        $button = (new Crawler($html))->filter('[data-test-view-in-store]');
        $this->assertCount(1, $button);

        return $button;
    }
}
