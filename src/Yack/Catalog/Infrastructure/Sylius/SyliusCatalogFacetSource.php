<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Sylius;

use App\Yack\Catalog\Domain\CatalogFacetSource;
use App\Yack\Catalog\Domain\ProductFacetValues;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Infrastructure\Api\ProductAttributeFilterExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter: filterable attribute values and price (lowest price of the enabled variants in the
 * channel, cents turned into euros) of the enabled products of the current channel, in two scalar
 * queries (no entity hydrated). The kind is the menu branch of the main category, as for the
 * `yack_type` filter of the product listing.
 */
#[AsAlias(CatalogFacetSource::class)]
final readonly class SyliusCatalogFacetSource implements CatalogFacetSource
{
    /** Key of the price among the values read for a product (not an attribute code). */
    private const PRICE = '#price';

    /**
     * @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository
     * @param class-string                             $productClass
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChannelContextInterface $channelContext,
        #[Autowire(service: 'sylius.repository.taxon')]
        private TaxonRepositoryInterface $taxonRepository,
        #[Autowire('%sylius.model.product.class%')]
        private string $productClass,
    ) {
    }

    public function listedProducts(?ProductKind $kind): array
    {
        $attributes = $this->listed($kind);
        $prices = $this->listed($kind);
        if (null === $attributes || null === $prices) {
            return [];
        }

        $attributes
            ->select('product.id AS productId', 'attribute.code AS code', 'attributeValue.text AS text', 'attributeValue.float AS number')
            ->innerJoin('product.attributes', 'attributeValue')
            ->innerJoin('attributeValue.attribute', 'attribute')
            ->andWhere('attribute.code IN (:codes)')
            ->setParameter('codes', [
                ProductAttributeFilterExtension::ATTRIBUTE_BRAND,
                ProductAttributeFilterExtension::ATTRIBUTE_RANGE,
                ProductAttributeFilterExtension::ATTRIBUTE_POWER,
                ProductAttributeFilterExtension::ATTRIBUTE_REFRIGERANT,
            ])
        ;
        $prices
            ->select('product.id AS productId', 'MIN(pricing.price) AS price')
            ->innerJoin('product.variants', 'variant')
            ->innerJoin('variant.channelPricings', 'pricing')
            ->andWhere('variant.enabled = true')
            ->andWhere('pricing.channelCode = :channelCode')
            ->setParameter('channelCode', $this->channelContext->getChannel()->getCode())
            ->groupBy('product.id')
        ;

        /** @var array<int|string, array<string, mixed>> $products */
        $products = [];
        foreach ($attributes->getQuery()->getArrayResult() as $row) {
            if (!is_array($row) || !is_scalar($row['productId'] ?? null) || !is_string($row['code'] ?? null)) {
                continue;
            }
            $products[(string) $row['productId']][$row['code']] = ProductAttributeFilterExtension::ATTRIBUTE_POWER === $row['code']
                ? ($row['number'] ?? null)
                : ($row['text'] ?? null);
        }
        foreach ($prices->getQuery()->getArrayResult() as $row) {
            if (is_array($row) && is_scalar($row['productId'] ?? null) && is_numeric($row['price'] ?? null)) {
                $products[(string) $row['productId']][self::PRICE] = (int) $row['price'] / 100;
            }
        }

        return array_values(array_map(static fn (array $values): ProductFacetValues => new ProductFacetValues(
            self::text($values[ProductAttributeFilterExtension::ATTRIBUTE_BRAND] ?? null),
            self::text($values[ProductAttributeFilterExtension::ATTRIBUTE_RANGE] ?? null),
            is_numeric($values[ProductAttributeFilterExtension::ATTRIBUTE_POWER] ?? null) ? (float) $values[ProductAttributeFilterExtension::ATTRIBUTE_POWER] : null,
            self::text($values[ProductAttributeFilterExtension::ATTRIBUTE_REFRIGERANT] ?? null),
            is_numeric($values[self::PRICE] ?? null) ? (float) $values[self::PRICE] : null,
        ), $products));
    }

    /** Enabled products of the channel, of the kind; null when the branch of the kind is missing. */
    private function listed(?ProductKind $kind): ?QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from($this->productClass, 'product')
            ->andWhere('product.enabled = true')
            ->andWhere(':channel MEMBER OF product.channels')
            ->setParameter('channel', $this->channelContext->getChannel())
        ;
        if (null === $kind) {
            return $queryBuilder;
        }

        $branch = $this->taxonRepository->findOneBy(['code' => $kind->catalogTaxonCode()]);
        if (!$branch instanceof TaxonInterface) {
            return null;
        }

        return $queryBuilder
            ->innerJoin('product.mainTaxon', 'mainTaxon')
            ->andWhere('mainTaxon.root = :root')
            ->andWhere('mainTaxon.left >= :left')
            ->andWhere('mainTaxon.right <= :right')
            ->setParameter('root', $branch->getRoot())
            ->setParameter('left', $branch->getLeft())
            ->setParameter('right', $branch->getRight())
        ;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
