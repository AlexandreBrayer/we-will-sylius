<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductReference;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Facade (ADR 008): reads the Sylius product by its code (read only), in the shop locale; the
 * normalisation of the printed reference and of the document names stays in the Domain.
 */
final readonly class ProductReferenceReader implements ProductReferenceReaderInterface
{
    private const BRAND_ATTRIBUTE = 'marque';

    /** @param ProductRepositoryInterface<ProductInterface> $products */
    public function __construct(
        #[Autowire(service: 'sylius.repository.product')]
        private ProductRepositoryInterface $products,
        #[Autowire('%app.yack.locale%')]
        private string $localeCode,
    ) {
    }

    public function find(string $printedReference): ?ProductReference
    {
        foreach (ProductReference::candidates($printedReference) as $code) {
            $product = $this->products->findOneByCode($code);
            if ($product instanceof ProductInterface && $product->isEnabled()) {
                return $this->reference($product);
            }
        }

        return null;
    }

    private function reference(ProductInterface $product): ?ProductReference
    {
        $product->setCurrentLocale($this->localeCode);
        $product->setFallbackLocale($this->localeCode);
        $slug = $product->getSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }

        $brand = $this->attribute($product, self::BRAND_ATTRIBUTE);

        return new ProductReference(
            (string) $product->getCode(),
            (string) $product->getName(),
            $slug,
            \is_string($brand) && '' !== $brand ? $brand : null,
            ProductReference::documentsFrom($this->attribute($product, ProductReference::DOCUMENTATION_ATTRIBUTE)),
        );
    }

    private function attribute(ProductInterface $product, string $code): mixed
    {
        foreach ($product->getAttributes() as $value) {
            if ($value instanceof ProductAttributeValueInterface && $value->getCode() === $code) {
                return $value->getValue();
            }
        }

        return null;
    }
}
