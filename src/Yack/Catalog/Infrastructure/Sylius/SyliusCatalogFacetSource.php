<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Sylius;

use App\Yack\Catalog\Domain\CatalogFacetSource;
use App\Yack\Catalog\Domain\ProductFacetValues;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Infrastructure\Api\ProductAttributeFilterExtension;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter: filterable attribute values of the enabled products of the current channel, in one
 * scalar query (no entity hydrated). The kind is the menu branch of the main category, as for
 * the `yack_type` filter of the product listing.
 */
#[AsAlias(CatalogFacetSource::class)]
final readonly class SyliusCatalogFacetSource implements CatalogFacetSource
{
    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
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
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('product.id AS productId', 'attribute.code AS code', 'attributeValue.text AS text', 'attributeValue.float AS number')
            ->from($this->productClass, 'product')
            ->innerJoin('product.attributes', 'attributeValue')
            ->innerJoin('attributeValue.attribute', 'attribute')
            ->andWhere('product.enabled = true')
            ->andWhere(':channel MEMBER OF product.channels')
            ->andWhere('attribute.code IN (:codes)')
            ->setParameter('channel', $this->channelContext->getChannel())
            ->setParameter('codes', [
                ProductAttributeFilterExtension::ATTRIBUTE_BRAND,
                ProductAttributeFilterExtension::ATTRIBUTE_RANGE,
                ProductAttributeFilterExtension::ATTRIBUTE_POWER,
                ProductAttributeFilterExtension::ATTRIBUTE_REFRIGERANT,
            ])
        ;

        if (null !== $kind) {
            $branch = $this->taxonRepository->findOneBy(['code' => $kind->catalogTaxonCode()]);
            if (!$branch instanceof TaxonInterface) {
                return [];
            }

            $queryBuilder
                ->innerJoin('product.mainTaxon', 'mainTaxon')
                ->andWhere('mainTaxon.root = :root')
                ->andWhere('mainTaxon.left >= :left')
                ->andWhere('mainTaxon.right <= :right')
                ->setParameter('root', $branch->getRoot())
                ->setParameter('left', $branch->getLeft())
                ->setParameter('right', $branch->getRight())
            ;
        }

        /** @var array<int|string, array<string, mixed>> $products */
        $products = [];
        foreach ($queryBuilder->getQuery()->getArrayResult() as $row) {
            if (!is_array($row) || !is_scalar($row['productId'] ?? null) || !is_string($row['code'] ?? null)) {
                continue;
            }
            $products[(string) $row['productId']][$row['code']] = ProductAttributeFilterExtension::ATTRIBUTE_POWER === $row['code']
                ? ($row['number'] ?? null)
                : ($row['text'] ?? null);
        }

        return array_values(array_map(static fn (array $values): ProductFacetValues => new ProductFacetValues(
            self::text($values[ProductAttributeFilterExtension::ATTRIBUTE_BRAND] ?? null),
            self::text($values[ProductAttributeFilterExtension::ATTRIBUTE_RANGE] ?? null),
            is_numeric($values[ProductAttributeFilterExtension::ATTRIBUTE_POWER] ?? null) ? (float) $values[ProductAttributeFilterExtension::ATTRIBUTE_POWER] : null,
            self::text($values[ProductAttributeFilterExtension::ATTRIBUTE_REFRIGERANT] ?? null),
        ), $products));
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
