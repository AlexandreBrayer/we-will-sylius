<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Yack\Catalog\Domain\ProductKind;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Shop API products: `?yack_type=product` (Clik&Yack finished products) or `?yack_type=part`
 * (Clik&Yack Parts spare parts) — products whose main category belongs to that menu branch.
 */
final readonly class ProductTypeFilterExtension implements QueryCollectionExtensionInterface
{
    public const QUERY_PARAMETER = 'yack_type';

    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        #[Autowire(service: 'sylius.section_resolver.uri_based')]
        private SectionProviderInterface $sectionProvider,
        #[Autowire(service: 'sylius.repository.taxon')]
        private TaxonRepositoryInterface $taxonRepository,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!is_a($resourceClass, ProductInterface::class, true) || !$this->sectionProvider->getSection() instanceof ShopApiSection) {
            return;
        }

        $filters = $context['filters'] ?? [];
        $type = is_array($filters) ? ($filters[self::QUERY_PARAMETER] ?? null) : null;
        if (!is_string($type) || '' === $type) {
            return;
        }

        $kind = ProductKind::fromApiType($type);
        $branch = null === $kind ? null : $this->taxonRepository->findOneBy(['code' => $kind->catalogTaxonCode()]);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!$branch instanceof TaxonInterface) {
            // Unknown type: no product.
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $mainTaxon = $queryNameGenerator->generateJoinAlias('yackMainTaxon');
        $root = $queryNameGenerator->generateParameterName('yackRoot');
        $left = $queryNameGenerator->generateParameterName('yackLeft');
        $right = $queryNameGenerator->generateParameterName('yackRight');

        $queryBuilder
            ->innerJoin(sprintf('%s.mainTaxon', $rootAlias), $mainTaxon)
            ->andWhere(sprintf('%s.root = :%s', $mainTaxon, $root))
            ->andWhere(sprintf('%s.left >= :%s', $mainTaxon, $left))
            ->andWhere(sprintf('%s.right <= :%s', $mainTaxon, $right))
            ->setParameter($root, $branch->getRoot())
            ->setParameter($left, $branch->getLeft())
            ->setParameter($right, $branch->getRight())
        ;
    }
}
