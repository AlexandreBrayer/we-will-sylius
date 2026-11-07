<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Doctrine;

use App\Yack\Catalog\Application\ProductKindResolverInterface;
use App\Yack\Catalog\Domain\CategoryDocument;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SearchDocument;
use App\Yack\Catalog\Domain\SearchDocumentSource;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter: products of the Yack channel and categories of the two menu branches (Clik&Yack,
 * Clik&Yack Parts), read from the Sylius catalogue in French. The typology is the kind of
 * product (finished product or spare part); the category path goes from the branch down.
 */
#[AsAlias(SearchDocumentSource::class)]
final readonly class DoctrineSearchDocumentSource implements SearchDocumentSource
{
    public const ATTRIBUTE_BRAND = 'marque';
    public const ATTRIBUTE_RANGE = 'gamme';
    public const ATTRIBUTE_MANUFACTURER_REFERENCE = 'reference_constructeur';

    /**
     * @param ProductRepositoryInterface<ProductInterface>  $productRepository
     * @param TaxonRepositoryInterface<TaxonInterface>      $taxonRepository
     * @param ChannelRepositoryInterface<ChannelInterface>  $channelRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.repository.product')]
        private ProductRepositoryInterface $productRepository,
        #[Autowire(service: 'sylius.repository.taxon')]
        private TaxonRepositoryInterface $taxonRepository,
        #[Autowire(service: 'sylius.repository.channel')]
        private ChannelRepositoryInterface $channelRepository,
        private ProductKindResolverInterface $productKindResolver,
        #[Autowire('%app.yack.channel_code%')]
        private string $channelCode,
        #[Autowire('%app.yack.locale%')]
        private string $locale,
    ) {
    }

    public function products(?array $codes = null): array
    {
        $channel = $this->channelRepository->findOneByCode($this->channelCode);
        if (!$channel instanceof ChannelInterface || [] === $codes) {
            return [];
        }

        $products = null === $codes ? $this->productRepository->findAll() : $this->productRepository->findBy(['code' => $codes]);
        $documents = [];
        foreach ($products as $product) {
            if ($product instanceof ProductInterface && null !== $product->getCode() && $product->hasChannel($channel)) {
                $documents[] = $this->document($product, $product->getCode());
            }
        }

        return $documents;
    }

    public function categories(): array
    {
        $documents = [];
        foreach (ProductKind::cases() as $kind) {
            $branch = $this->taxonRepository->findOneBy(['code' => $kind->catalogTaxonCode()]);
            if ($branch instanceof TaxonInterface) {
                $this->collect($branch, $kind, [], $documents);
            }
        }

        return $documents;
    }

    private function document(ProductInterface $product, string $code): SearchDocument
    {
        $translation = $product->getTranslation($this->locale);
        $kind = $this->productKindResolver->kindOf($product);
        [$categoryCodes, $categoryPath] = $this->ancestry($product->getMainTaxon(), $kind);
        $image = $product->getImagesByType('main')->first() ?: $product->getImages()->first();

        return new SearchDocument(
            code: $code,
            name: self::text($translation->getName()) ?? $code,
            slug: (string) $translation->getSlug(),
            enabled: $product->isEnabled(),
            kind: $kind,
            brand: $this->attribute($product, self::ATTRIBUTE_BRAND),
            range: $this->attribute($product, self::ATTRIBUTE_RANGE),
            typology: $kind?->label(),
            manufacturerReference: $this->attribute($product, self::ATTRIBUTE_MANUFACTURER_REFERENCE),
            categoryCodes: $categoryCodes,
            categoryPath: $categoryPath,
            shortDescription: $translation instanceof ProductTranslationInterface ? self::text($translation->getShortDescription()) : null,
            imagePath: $image instanceof ImageInterface ? $image->getPath() : null,
        );
    }

    /**
     * Codes of the category and of its ancestors (nearest first) and their names (from the
     * branch of the kind down), the ancestors above the branch left out.
     *
     * @return array{list<string>, list<string>}
     */
    private function ancestry(?TaxonInterface $taxon, ?ProductKind $kind): array
    {
        $codes = [];
        $names = [];
        while (null !== $taxon) {
            $code = $taxon->getCode();
            if (null !== $code) {
                $codes[] = $code;
            }
            $name = self::text($taxon->getTranslation($this->locale)->getName());
            if (null !== $name) {
                $names[] = $name;
            }
            if (null !== $kind && $code === $kind->catalogTaxonCode()) {
                break;
            }
            $taxon = $taxon->getParent();
        }

        return [$codes, array_reverse($names)];
    }

    /**
     * Enabled categories of a branch, depth first (a disabled category hides its children).
     *
     * @param list<string>           $path
     * @param list<CategoryDocument> $documents
     */
    private function collect(TaxonInterface $taxon, ProductKind $kind, array $path, array &$documents): void
    {
        if (!$taxon->isEnabled()) {
            return;
        }

        $translation = $taxon->getTranslation($this->locale);
        $name = self::text($translation->getName()) ?? (string) $taxon->getCode();
        $slug = $translation->getSlug();
        if (null !== $taxon->getCode() && null !== $slug) {
            $documents[] = new CategoryDocument($taxon->getCode(), $name, $slug, $kind, $path);
        }

        foreach ($taxon->getChildren() as $child) {
            if ($child instanceof TaxonInterface) {
                $this->collect($child, $kind, [...$path, $name], $documents);
            }
        }
    }

    private function attribute(ProductInterface $product, string $code): ?string
    {
        foreach ($product->getAttributes() as $value) {
            if ($value instanceof ProductAttributeValueInterface && $value->getCode() === $code && is_scalar($value->getValue())) {
                return self::text((string) $value->getValue());
            }
        }

        return null;
    }

    private static function text(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}
