<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Sylius;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SourceProduct;
use App\Yack\Catalog\Infrastructure\Sylius\SyliusProductCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductImage;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Real Sylius models, stubs of the repositories and factories (interfaces).
 *
 * @internal
 */
#[CoversClass(SyliusProductCatalog::class)]
final class SyliusProductCatalogTest extends TestCase
{
    /** @var array<string, ProductInterface> */
    private array $products = [];

    /** @var array<string, TaxonInterface> */
    private array $taxons = [];

    /** @var array<string, ProductAttributeInterface> */
    private array $attributes = [];

    /** @var array<string, int> */
    private array $lookups = [];

    private ?Channel $channel;

    private ?TaxCategory $taxCategory;

    private ?ProductInterface $created = null;

    private EntityManagerInterface&MockObject $entityManager;

    private ImageUploaderInterface&MockObject $uploader;

    private string $image;

    #[\Override]
    protected function setUp(): void
    {
        $this->channel = new Channel();
        $this->channel->setCode('YACK');
        $this->taxCategory = new TaxCategory();
        $this->taxCategory->setCode('tva_20');
        foreach (['pac_air_air', 'marque_mhi', 'marque_daikin'] as $code) {
            $this->taxons[$code] = self::taxon($code);
        }
        $this->attributes = [
            'type_article' => self::attribute('type_article', AttributeValueInterface::STORAGE_TEXT, translatable: true),
            'puissance_kw' => self::attribute('puissance_kw', AttributeValueInterface::STORAGE_FLOAT),
            'capacite_l' => self::attribute('capacite_l', AttributeValueInterface::STORAGE_INTEGER),
            'wifi' => self::attribute('wifi', AttributeValueInterface::STORAGE_BOOLEAN),
        ];
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->uploader = $this->createMock(ImageUploaderInterface::class);
        $this->image = (string) tempnam(sys_get_temp_dir(), 'yack-photo');
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->image)) {
            unlink($this->image);
        }
    }

    public function testAnUnknownProductHasNoCurrentState(): void
    {
        $this->assertNull($this->catalog()->current('YK-ABSENT'));
    }

    public function testTheCurrentStateOfAProductIsReadInTheLocaleAndChannelOfYack(): void
    {
        $product = $this->product('SRK25ZS-W', priceInCents: 59490, stock: 12);
        $product->setShortDescription('Accroche');
        $product->setDescription('Description');
        $product->setMainTaxon($this->taxons['pac_air_air']);
        $product->addAttribute(self::value($this->attributes['puissance_kw'], 2.5));
        $product->addAttribute(self::value($this->attributes['type_article'], 'Produit fini', 'fr_FR'));
        $product->addAttribute(self::value($this->attributes['type_article'], 'Finished product', 'en_US'));
        $product->addAttribute(self::value(self::attribute('documents', AttributeValueInterface::STORAGE_JSON), ['notice.pdf']));
        $this->products['SRK25ZS-W'] = $product;

        $current = $this->catalog()->current('SRK25ZS-W');

        $this->assertSame(
            ['SRK25ZS-W', 'Console SRK25ZS-W', true, 59490, 12, 'pac_air_air', ['puissance_kw' => 2.5, 'type_article' => 'Produit fini'], 'Accroche', 'Description'],
            [$current?->code, $current?->name, $current?->active, $current?->priceInCents, $current?->stock, $current?->mainTaxonCode, $current?->attributes, $current?->shortDescription, $current?->description],
        );
    }

    public function testAProductWithoutVariantHasNeitherPriceNorStock(): void
    {
        $product = new Product();
        $product->setCurrentLocale('fr_FR');
        $product->setFallbackLocale('fr_FR');
        $product->setCode('YK-NU');
        $product->setEnabled(false);
        $this->products['YK-NU'] = $product;

        $current = $this->catalog()->current('YK-NU');

        $this->assertSame(['', false, null, null, null], [$current?->name, $current?->active, $current?->priceInCents, $current?->stock, $current?->mainTaxonCode]);
    }

    public function testCheckingAValidRecordLooksEachReferenceUpOnce(): void
    {
        $catalog = $this->catalog();

        $catalog->check(self::source(taxonCodes: ['marque_mhi', 'pac_air_air'], imagePath: $this->image));
        $catalog->check(self::source('YK-2', taxonCodes: ['marque_mhi']));

        $this->assertSame(['taxon:pac_air_air' => 1, 'taxon:marque_mhi' => 1, 'attribute:type_article' => 1], $this->lookups);
    }

    /** @param array<string, string|int|float|bool> $attributes */
    #[DataProvider('provideUnknownReferences')]
    public function testARecordWithAnUnknownReferenceIsRejectedBeforeAnyChange(array $taxonCodes, array $attributes, ?string $imagePath, string $message): void
    {
        $this->products['SRK25ZS-W'] = $product = $this->product('SRK25ZS-W', priceInCents: 100, stock: 1);

        try {
            $this->catalog()->apply(self::source(taxonCodes: $taxonCodes, attributes: $attributes, imagePath: $imagePath));
            $this->fail('The record must be rejected.');
        } catch (InvalidSourceProduct $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
        $this->assertSame('Console SRK25ZS-W', $product->getName());
    }

    /** @return \Iterator<string, array{list<string>, array<string, string|int|float|bool>, string|null, string}> */
    public static function provideUnknownReferences(): \Iterator
    {
        yield 'marque inconnue' => [['marque_lg'], [], null, 'Produit « SRK25ZS-W » : catégorie ou marque « marque_lg » inconnue.'];
        yield 'attribut inconnu' => [[], ['couleur' => 'blanc'], null, 'Produit « SRK25ZS-W » : attribut « couleur » inconnu.'];
        yield 'image absente' => [[], [], '/nulle/part/photo.jpg', 'Produit « SRK25ZS-W » : image « photo.jpg » introuvable.'];
    }

    public function testANewProductIsCreatedInTheChannelWithItsTaxonsAttributesPriceStockAndImage(): void
    {
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Product::class));
        $this->uploader->expects($this->once())->method('upload')->with($this->callback(fn (ImageInterface $image): bool => 'main' === $image->getType() && $this->image === $image->getFile()?->getPathname()));

        $this->catalog()->apply(self::source(
            taxonCodes: ['marque_mhi'],
            attributes: ['puissance_kw' => '2.5', 'capacite_l' => '200', 'wifi' => 1],
            imagePath: $this->image,
        ));

        $product = $this->created;
        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame(['SRK25ZS-W', 'Console murale', 'Accroche', null, 'console-murale-srk25zs-w', true, true], [$product->getCode(), $product->getName(), $product->getShortDescription(), $product->getDescription(), $product->getSlug(), $product->isEnabled(), $product->hasChannel($this->channel)]);
        $this->assertSame([['pac_air_air', 0], ['marque_mhi', 1]], array_map(static fn (ProductTaxonInterface $taxon): array => [$taxon->getTaxon()?->getCode(), $taxon->getPosition()], $product->getProductTaxons()->toArray()));
        $this->assertSame('pac_air_air', $product->getMainTaxon()?->getCode());
        $this->assertSame(
            [['type_article', 'fr_FR', 'Produit fini'], ['puissance_kw', null, 2.5], ['capacite_l', null, 200], ['wifi', null, true]],
            array_values(array_map(static fn (AttributeValueInterface $value): array => [$value->getCode(), $value->getLocaleCode(), $value->getValue()], $product->getAttributes()->toArray())),
        );
        $variant = $product->getVariants()->first();
        $this->assertInstanceOf(ProductVariant::class, $variant);
        $this->assertSame(['SRK25ZS-W', true, 12, true, $this->taxCategory], [$variant->getCode(), $variant->isTracked(), $variant->getOnHand(), $variant->isShippingRequired(), $variant->getTaxCategory()]);
        $this->assertSame([59490, null], [$variant->getChannelPricingForChannel($this->channel)?->getPrice(), $variant->getChannelPricingForChannel($this->channel)?->getOriginalPrice()]);
        $this->assertTrue($product->hasImages());
    }

    public function testAnExistingProductMirrorsTheSourceWithoutDuplicatingAnything(): void
    {
        $product = $this->product('SRK25ZS-W', priceInCents: 50000, stock: 3);
        $product->setSlug('slug-historique');
        $product->addChannel($this->channel);
        $product->addProductTaxon(self::productTaxon($this->taxons['pac_air_air'], 0));
        $product->addProductTaxon(self::productTaxon($this->taxons['marque_daikin'], 1));
        $product->addAttribute($power = self::value($this->attributes['puissance_kw'], 2.0));
        $product->addImage(new ProductImage());
        $variant = $product->getVariants()->first();
        $this->assertInstanceOf(ProductVariant::class, $variant);
        $variant->getChannelPricingForChannel($this->channel)?->setOriginalPrice(60000);
        $this->products['SRK25ZS-W'] = $product;
        $this->entityManager->expects($this->never())->method('persist');
        $this->uploader->expects($this->never())->method('upload');

        $this->catalog()->apply(self::source(active: false, taxonCodes: ['marque_mhi'], attributes: ['puissance_kw' => 2.5], imagePath: $this->image));

        $this->assertSame(['slug-historique', false, 1], [$product->getSlug(), $product->isEnabled(), $product->getChannels()->count()]);
        $this->assertSame(['pac_air_air', 'marque_mhi'], array_values(array_map(static fn (ProductTaxonInterface $taxon): ?string => $taxon->getTaxon()?->getCode(), $product->getProductTaxons()->toArray())));
        $this->assertSame([2, 2.5], [$product->getAttributes()->count(), $power->getValue()]);
        $this->assertSame([12, 59490, null, 1], [$variant->getOnHand(), $variant->getChannelPricingForChannel($this->channel)?->getPrice(), $variant->getChannelPricingForChannel($this->channel)?->getOriginalPrice(), $variant->getChannelPricings()->count()]);
    }

    public function testAProductWithoutVariantCannotBeApplied(): void
    {
        $product = new Product();
        $product->setCode('SRK25ZS-W');
        $this->products['SRK25ZS-W'] = $product;

        $this->expectException(InvalidSourceProduct::class);
        $this->expectExceptionMessage('Produit « SRK25ZS-W » : aucune déclinaison.');

        $this->catalog()->apply(self::source());
    }

    public function testTheYackChannelMustExist(): void
    {
        $this->channel = null;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Channel "YACK" does not exist, load the yack fixtures first.');

        $this->catalog()->apply(self::source());
    }

    public function testTheTaxCategoryMustExist(): void
    {
        $this->taxCategory = null;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Tax category "tva_20" does not exist.');

        $this->catalog()->apply(self::source());
    }

    public function testTheCommitFlushesAndForgetsTheReferencesLookedUp(): void
    {
        $this->entityManager->expects($this->once())->method('flush');
        $catalog = $this->catalog();
        $catalog->check(self::source());

        $catalog->commit();
        $catalog->check(self::source());

        $this->assertSame(['taxon:pac_air_air' => 2, 'attribute:type_article' => 2], $this->lookups);
    }

    private function catalog(): SyliusProductCatalog
    {
        $productFactory = $this->createStub(ProductFactoryInterface::class);
        $productFactory->method('createWithVariant')->willReturnCallback(function (): ProductInterface {
            $product = new Product();
            $product->addVariant(new ProductVariant());

            return $this->created = $product;
        });
        $productTaxonFactory = $this->createStub(FactoryInterface::class);
        $productTaxonFactory->method('createNew')->willReturnCallback(static fn (): ProductTaxon => new ProductTaxon());
        $channelPricingFactory = $this->createStub(FactoryInterface::class);
        $channelPricingFactory->method('createNew')->willReturnCallback(static fn (): ChannelPricing => new ChannelPricing());
        $attributeValueFactory = $this->createStub(FactoryInterface::class);
        $attributeValueFactory->method('createNew')->willReturnCallback(static fn (): ProductAttributeValue => new ProductAttributeValue());
        $imageFactory = $this->createStub(FactoryInterface::class);
        $imageFactory->method('createNew')->willReturnCallback(static fn (): ProductImage => new ProductImage());
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findOneBy')->willReturnCallback(fn (array $criteria): ?ProductInterface => $this->products[$criteria['code']] ?? null);
        $taxonRepository = $this->createStub(TaxonRepositoryInterface::class);
        $taxonRepository->method('findOneBy')->willReturnCallback(fn (array $criteria): ?TaxonInterface => $this->lookUp('taxon', $criteria['code'], $this->taxons));
        $attributeRepository = $this->createStub(RepositoryInterface::class);
        $attributeRepository->method('findOneBy')->willReturnCallback(fn (array $criteria): ?ProductAttributeInterface => $this->lookUp('attribute', $criteria['code'], $this->attributes));
        $channelRepository = $this->createStub(ChannelRepositoryInterface::class);
        $channelRepository->method('findOneByCode')->willReturnCallback(fn (string $code): ?Channel => 'YACK' === $code ? $this->channel : null);
        $taxCategoryRepository = $this->createStub(RepositoryInterface::class);
        $taxCategoryRepository->method('findOneBy')->willReturnCallback(fn (array $criteria): ?TaxCategory => 'tva_20' === $criteria['code'] ? $this->taxCategory : null);
        $slugGenerator = $this->createStub(SlugGeneratorInterface::class);
        $slugGenerator->method('generate')->willReturnCallback(static fn (string $name): string => strtolower(str_replace(' ', '-', $name)));

        return new SyliusProductCatalog(
            $productFactory,
            $productTaxonFactory,
            $channelPricingFactory,
            $attributeValueFactory,
            $imageFactory,
            $productRepository,
            $taxonRepository,
            $attributeRepository,
            $channelRepository,
            $taxCategoryRepository,
            $this->uploader,
            $slugGenerator,
            $this->entityManager,
            'YACK',
            'fr_FR',
            'tva_20',
        );
    }

    /**
     * @template T of object
     *
     * @param array<string, T> $known
     *
     * @return T|null
     */
    private function lookUp(string $kind, string $code, array $known): ?object
    {
        $key = $kind . ':' . $code;
        $this->lookups[$key] = ($this->lookups[$key] ?? 0) + 1;

        return $known[$code] ?? null;
    }

    private function product(string $code, int $priceInCents, int $stock): Product
    {
        $product = new Product();
        $product->setCurrentLocale('fr_FR');
        $product->setFallbackLocale('fr_FR');
        $product->setCode($code);
        $product->setName('Console ' . $code);
        $variant = new ProductVariant();
        $variant->setOnHand($stock);
        $pricing = new ChannelPricing();
        $pricing->setChannelCode('YACK');
        $pricing->setPrice($priceInCents);
        $variant->addChannelPricing($pricing);
        $product->addVariant($variant);

        return $product;
    }

    private static function taxon(string $code): Taxon
    {
        $taxon = new Taxon();
        $taxon->setCode($code);

        return $taxon;
    }

    private static function productTaxon(TaxonInterface $taxon, int $position): ProductTaxon
    {
        $productTaxon = new ProductTaxon();
        $productTaxon->setTaxon($taxon);
        $productTaxon->setPosition($position);

        return $productTaxon;
    }

    private static function attribute(string $code, string $storageType, bool $translatable = false): ProductAttribute
    {
        $attribute = new ProductAttribute();
        $attribute->setCode($code);
        $attribute->setStorageType($storageType);
        $attribute->setTranslatable($translatable);

        return $attribute;
    }

    private static function value(ProductAttributeInterface $attribute, mixed $value, ?string $localeCode = null): ProductAttributeValue
    {
        $attributeValue = new ProductAttributeValue();
        $attributeValue->setAttribute($attribute);
        $attributeValue->setLocaleCode($localeCode);
        $attributeValue->setValue($value);

        return $attributeValue;
    }

    /**
     * @param list<string>                         $taxonCodes
     * @param array<string, string|int|float|bool> $attributes
     */
    private static function source(string $code = 'SRK25ZS-W', bool $active = true, array $taxonCodes = [], array $attributes = [], ?string $imagePath = null): SourceProduct
    {
        return new SourceProduct($code, 'Console murale', $active, ProductKind::Finished, 'pac_air_air', 59490, 12, $taxonCodes, $attributes, 'Accroche', null, $imagePath);
    }
}
