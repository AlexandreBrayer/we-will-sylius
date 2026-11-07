<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use App\Yack\Catalog\Application\ProductKindResolverInterface;
use App\Yack\Catalog\Application\VariantStockReaderInterface;
use App\Yack\Catalog\Domain\OnlineSale;
use Sylius\Bundle\ApiBundle\Serializer\ContextKeys;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Shop API product (listing and detail): adds a "yack" block with the Yack catalogue data:
 * kind (finished product / spare part), brand, range, technical attributes, stock (with its level)
 * and whether the product is sold online. Prices stay on the variant data (hidden for visitors and
 * PRO customers); a product not sold online has no price for anybody.
 */
#[AsDecorator('sylius_api.normalizer.product')]
final readonly class YackProductNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    public const BRANDS_TAXON_CODE = 'marques';

    /** Price fields of `defaultVariantData` emptied for a product not sold online. */
    public const PRICE_FIELDS = ['price', 'originalPrice', 'lowestPriceBeforeDiscount'];

    public function __construct(
        #[AutowireDecorated]
        private NormalizerInterface&NormalizerAwareInterface $inner,
        #[Autowire(service: 'sylius.resolver.product_variant')]
        private ProductVariantResolverInterface $variantResolver,
        private VariantStockReaderInterface $variantStock,
        private ProductKindResolverInterface $productKindResolver,
        #[Autowire('%app.yack.locale%')]
        private string $defaultLocaleCode,
    ) {
    }

    /** @return array<string, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->inner->normalize($data, $format, $context);
        if (!is_array($normalized) || !$data instanceof ProductInterface) {
            return $normalized;
        }

        $localeCode = $context[ContextKeys::LOCALE_CODE] ?? $this->defaultLocaleCode;
        $kind = $this->productKindResolver->kindOf($data);
        $variant = $this->variantResolver->getVariant($data);
        $soldOnline = OnlineSale::isSoldOnline($this->attributeValue($data, OnlineSale::ATTRIBUTE));
        if (!$soldOnline && is_array($normalized['defaultVariantData'] ?? null)) {
            foreach (self::PRICE_FIELDS as $field) {
                $normalized['defaultVariantData'][$field] = null;
            }
            unset($normalized['defaultVariantData']['appliedPromotions']);
        }

        $normalized['yack'] = [
            'type' => $kind?->apiType(),
            'typeLabel' => $kind?->label(),
            'brand' => $this->brand($data),
            'range' => $this->attributeValue($data, 'gamme'),
            'soldOnline' => $soldOnline,
            'attributes' => $this->attributes($data, is_string($localeCode) ? $localeCode : $this->defaultLocaleCode),
            'stock' => $this->variantStock->of($variant instanceof ProductVariantInterface ? $variant : null)->toArray(),
        ];

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->inner->setNormalizer($normalizer);
    }

    /**
     * Brand category (filterable: ?productTaxons.taxon.code=marque_mhi), else the "marque" attribute.
     *
     * @return array{code: string|null, name: string|null}|null
     */
    private function brand(ProductInterface $product): ?array
    {
        foreach ($product->getTaxons() as $taxon) {
            if (self::BRANDS_TAXON_CODE === $taxon->getParent()?->getCode()) {
                return ['code' => $taxon->getCode(), 'name' => $taxon->getName()];
            }
        }

        $name = $this->attributeValue($product, 'marque');

        return is_string($name) ? ['code' => null, 'name' => $name] : null;
    }

    private function attributeValue(ProductInterface $product, string $code): mixed
    {
        foreach ($product->getAttributes() as $attributeValue) {
            if ($attributeValue instanceof ProductAttributeValueInterface && $attributeValue->getCode() === $code) {
                return $attributeValue->getValue();
            }
        }

        return null;
    }

    /** @return list<array{code: string|null, name: string|null, type: string|null, value: mixed}> */
    private function attributes(ProductInterface $product, string $localeCode): array
    {
        $attributes = [];
        foreach ($product->getAttributesByLocale($localeCode, $this->defaultLocaleCode) as $attributeValue) {
            if (!$attributeValue instanceof ProductAttributeValueInterface) {
                continue;
            }
            $attributes[] = [
                'code' => $attributeValue->getCode(),
                'name' => $attributeValue->getName(),
                'type' => $attributeValue->getType(),
                'value' => $attributeValue->getValue(),
            ];
        }

        return $attributes;
    }
}
