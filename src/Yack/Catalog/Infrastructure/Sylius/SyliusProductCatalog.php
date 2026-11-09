<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Sylius;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductCatalog;
use App\Yack\Catalog\Domain\SourceProduct;
use App\Yack\Catalog\Domain\SyncOutcome;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adapter: writes Yack products into the Sylius catalog of the Yack channel.
 *
 * Every reference (taxons, attributes, image) is resolved before touching the entity, so a
 * rejected record never leaves a half-updated product behind.
 */
#[AsAlias(ProductCatalog::class)]
final class SyliusProductCatalog implements ProductCatalog, ResetInterface
{
    public const ATTRIBUTE_PRODUCT_KIND = 'type_article';

    private ?ChannelInterface $channel = null;

    private ?TaxCategoryInterface $taxCategory = null;

    /** @var array<string, ProductAttributeInterface|null> */
    private array $attributes = [];

    /** @var array<string, TaxonInterface|null> */
    private array $taxons = [];

    /**
     * @param ProductFactoryInterface<ProductInterface>                $productFactory
     * @param FactoryInterface<ProductTaxonInterface>                  $productTaxonFactory
     * @param FactoryInterface<ChannelPricingInterface>                $channelPricingFactory
     * @param FactoryInterface<ProductAttributeValueInterface>         $attributeValueFactory
     * @param FactoryInterface<ImageInterface>                         $productImageFactory
     * @param ProductRepositoryInterface<ProductInterface>             $productRepository
     * @param TaxonRepositoryInterface<TaxonInterface>                 $taxonRepository
     * @param RepositoryInterface<ProductAttributeInterface>           $attributeRepository
     * @param ChannelRepositoryInterface<ChannelInterface>             $channelRepository
     * @param RepositoryInterface<TaxCategoryInterface>                $taxCategoryRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.factory.product')]
        private readonly ProductFactoryInterface $productFactory,
        #[Autowire(service: 'sylius.factory.product_taxon')]
        private readonly FactoryInterface $productTaxonFactory,
        #[Autowire(service: 'sylius.factory.channel_pricing')]
        private readonly FactoryInterface $channelPricingFactory,
        #[Autowire(service: 'sylius.factory.product_attribute_value')]
        private readonly FactoryInterface $attributeValueFactory,
        #[Autowire(service: 'sylius.factory.product_image')]
        private readonly FactoryInterface $productImageFactory,
        #[Autowire(service: 'sylius.repository.product')]
        private readonly ProductRepositoryInterface $productRepository,
        #[Autowire(service: 'sylius.repository.taxon')]
        private readonly TaxonRepositoryInterface $taxonRepository,
        #[Autowire(service: 'sylius.repository.product_attribute')]
        private readonly RepositoryInterface $attributeRepository,
        #[Autowire(service: 'sylius.repository.channel')]
        private readonly ChannelRepositoryInterface $channelRepository,
        #[Autowire(service: 'sylius.repository.tax_category')]
        private readonly RepositoryInterface $taxCategoryRepository,
        #[Autowire(service: 'sylius.uploader.image')]
        private readonly ImageUploaderInterface $imageUploader,
        #[Autowire(service: 'sylius.generator.slug')]
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%app.yack.channel_code%')]
        private readonly string $channelCode,
        #[Autowire('%app.yack.locale%')]
        private readonly string $localeCode,
        #[Autowire('%app.yack.tax_category_code%')]
        private readonly string $taxCategoryCode,
    ) {
    }

    public function apply(SourceProduct $source): SyncOutcome
    {
        // 1. Resolve everything first: no mutation before the record is known to be valid.
        $mainTaxon = $this->taxon($source->mainTaxonCode, $source->code);
        $taxons = [$mainTaxon];
        foreach ($source->taxonCodes as $taxonCode) {
            $taxons[] = $this->taxon($taxonCode, $source->code);
        }

        $attributeValues = [self::ATTRIBUTE_PRODUCT_KIND => $source->kind->label()] + $source->attributes;
        $attributes = [];
        foreach (array_keys($attributeValues) as $attributeCode) {
            $attributes[$attributeCode] = $this->attribute($attributeCode, $source->code);
        }

        if (null !== $source->imagePath && !is_file($source->imagePath)) {
            throw new InvalidSourceProduct(sprintf('Product "%s": image "%s" not found.', $source->code, $source->imagePath));
        }

        $channel = $this->channel();

        // 2. Mirror the source.
        /** @var ProductInterface|null $product */
        $product = $this->productRepository->findOneBy(['code' => $source->code]);
        $isNew = null === $product;
        if (null === $product) {
            $product = $this->productFactory->createWithVariant();
            $product->setCode($source->code);
        }

        $product->setCurrentLocale($this->localeCode);
        $product->setFallbackLocale($this->localeCode);
        $product->setName($source->name);
        $product->setShortDescription($source->shortDescription);
        $product->setDescription($source->description);
        if (null === $product->getSlug()) {
            $product->setSlug($this->slugGenerator->generate($source->name . ' ' . $source->code));
        }
        $product->setEnabled($source->active);
        if (!$product->hasChannel($channel)) {
            $product->addChannel($channel);
        }

        $this->syncTaxons($product, $mainTaxon, $taxons);
        $this->syncAttributes($product, $attributes, $attributeValues);
        $this->syncVariant($product, $source, $channel);

        if (null !== $source->imagePath && !$product->hasImages()) {
            $this->attachImage($product, $source->imagePath);
        }

        if ($isNew) {
            $this->entityManager->persist($product);
        }

        return match (true) {
            !$source->active => SyncOutcome::Disabled,
            $isNew => SyncOutcome::Created,
            default => SyncOutcome::Updated,
        };
    }

    public function commit(): void
    {
        $this->entityManager->flush();
        $this->reset();
    }

    public function reset(): void
    {
        $this->channel = null;
        $this->taxCategory = null;
        $this->attributes = [];
        $this->taxons = [];
    }

    /** @param list<TaxonInterface> $taxons */
    private function syncTaxons(ProductInterface $product, TaxonInterface $mainTaxon, array $taxons): void
    {
        $product->setMainTaxon($mainTaxon);

        foreach ($product->getProductTaxons() as $productTaxon) {
            if (!in_array($productTaxon->getTaxon(), $taxons, true)) {
                $product->removeProductTaxon($productTaxon);
            }
        }

        foreach ($taxons as $position => $taxon) {
            if ($product->hasTaxon($taxon)) {
                continue;
            }

            $productTaxon = $this->productTaxonFactory->createNew();
            $productTaxon->setTaxon($taxon);
            $productTaxon->setPosition($position);
            $product->addProductTaxon($productTaxon);
        }
    }

    /**
     * @param array<string, ProductAttributeInterface> $attributes
     * @param array<string, string|int|float|bool>     $values
     */
    private function syncAttributes(ProductInterface $product, array $attributes, array $values): void
    {
        foreach ($attributes as $code => $attribute) {
            $value = match ($attribute->getStorageType()) {
                'float' => (float) $values[$code],
                'integer' => (int) $values[$code],
                'boolean' => (bool) $values[$code],
                default => (string) $values[$code],
            };
            $localeCode = $attribute->isTranslatable() ? $this->localeCode : null;

            $attributeValue = $this->findAttributeValue($product, $code, $localeCode);
            if (null === $attributeValue) {
                $attributeValue = $this->attributeValueFactory->createNew();
                $attributeValue->setAttribute($attribute);
                $attributeValue->setLocaleCode($localeCode);
                $product->addAttribute($attributeValue);
            }

            $attributeValue->setValue($value);
        }
    }

    private function findAttributeValue(ProductInterface $product, string $code, ?string $localeCode): ?ProductAttributeValueInterface
    {
        foreach ($product->getAttributes() as $attributeValue) {
            if ($attributeValue instanceof ProductAttributeValueInterface && $attributeValue->getCode() === $code && $attributeValue->getLocaleCode() === $localeCode) {
                return $attributeValue;
            }
        }

        return null;
    }

    private function syncVariant(ProductInterface $product, SourceProduct $source, ChannelInterface $channel): void
    {
        /** @var ProductVariantInterface|false $variant */
        $variant = $product->getVariants()->first();
        if (false === $variant) {
            throw new InvalidSourceProduct(sprintf('Product "%s" has no variant.', $source->code));
        }

        $variant->setCode($source->code);
        $variant->setTracked(true);
        $variant->setOnHand($source->stock);
        $variant->setShippingRequired(true);
        $variant->setTaxCategory($this->taxCategory());

        $channelPricing = $variant->getChannelPricingForChannel($channel);
        if (null === $channelPricing) {
            $channelPricing = $this->channelPricingFactory->createNew();
            $channelPricing->setChannelCode($channel->getCode());
            $variant->addChannelPricing($channelPricing);
        }
        $channelPricing->setPrice($source->priceInCents);
        $channelPricing->setOriginalPrice(null);
    }

    private function attachImage(ProductInterface $product, string $path): void
    {
        $image = $this->productImageFactory->createNew();
        $image->setType('main');
        $image->setFile(new UploadedFile($path, basename($path), test: true));
        $this->imageUploader->upload($image);
        $product->addImage($image);
    }

    private function taxon(string $code, string $productCode): TaxonInterface
    {
        if (!array_key_exists($code, $this->taxons)) {
            $this->taxons[$code] = $this->taxonRepository->findOneBy(['code' => $code]);
        }

        return $this->taxons[$code]
            ?? throw new InvalidSourceProduct(sprintf('Product "%s": unknown category/brand taxon "%s".', $productCode, $code));
    }

    private function attribute(string $code, string $productCode): ProductAttributeInterface
    {
        if (!array_key_exists($code, $this->attributes)) {
            $this->attributes[$code] = $this->attributeRepository->findOneBy(['code' => $code]);
        }

        return $this->attributes[$code]
            ?? throw new InvalidSourceProduct(sprintf('Product "%s": unknown attribute "%s".', $productCode, $code));
    }

    private function channel(): ChannelInterface
    {
        if (null === $this->channel) {
            $channel = $this->channelRepository->findOneByCode($this->channelCode);
            if (!$channel instanceof ChannelInterface) {
                throw new \LogicException(sprintf('Channel "%s" does not exist, load the yack fixtures first.', $this->channelCode));
            }
            $this->channel = $channel;
        }

        return $this->channel;
    }

    private function taxCategory(): TaxCategoryInterface
    {
        return $this->taxCategory ??= $this->taxCategoryRepository->findOneBy(['code' => $this->taxCategoryCode])
            ?? throw new \LogicException(sprintf('Tax category "%s" does not exist.', $this->taxCategoryCode));
    }
}
