<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Yack\Catalog\Domain\CatalogFilter;
use App\Yack\Catalog\Domain\InvalidCatalogFilter;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Shop API products filtered on their attributes (CatalogFilterQuery): `?yack_brand[]=MHI&yack_brand[]=RHOSS`
 * ("marque"), `?yack_range[]=Evolys` ("gamme"), `?yack_power_band[]=6-9` ("puissance_kw" within a band of
 * `CatalogFilter::POWER_BANDS`), `?yack_refrigerant[]=R32` ("fluide_frigorigene"). Any value within a
 * parameter (texts compared trimmed, case aside, as the facet counts), all the parameters together.
 * An unknown power band answers 400.
 */
final readonly class ProductAttributeFilterExtension implements QueryCollectionExtensionInterface
{
    public const ATTRIBUTE_BRAND = 'marque';
    public const ATTRIBUTE_RANGE = 'gamme';
    public const ATTRIBUTE_POWER = 'puissance_kw';
    public const ATTRIBUTE_REFRIGERANT = 'fluide_frigorigene';

    /** @param class-string $attributeValueClass */
    public function __construct(
        #[Autowire(service: 'sylius.section_resolver.uri_based')]
        private SectionProviderInterface $sectionProvider,
        #[Autowire('%sylius.model.product_attribute_value.class%')]
        private string $attributeValueClass,
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
        try {
            $filter = CatalogFilterQuery::filter(is_array($filters) ? $filters : []);
        } catch (InvalidCatalogFilter $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
        if ($filter->isEmpty()) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        foreach ([self::ATTRIBUTE_BRAND => $filter->brands, self::ATTRIBUTE_RANGE => $filter->ranges, self::ATTRIBUTE_REFRIGERANT => $filter->refrigerants] as $code => $values) {
            if ([] !== $values) {
                $this->whereAttribute($queryBuilder, $queryNameGenerator, $rootAlias, $code, static function (string $value) use ($queryBuilder, $queryNameGenerator, $values): string {
                    $parameter = $queryNameGenerator->generateParameterName('yackAttributeText');
                    $queryBuilder->setParameter($parameter, array_map(mb_strtolower(...), $values));

                    return sprintf('LOWER(TRIM(%s.text)) IN (:%s)', $value, $parameter);
                });
            }
        }
        if ([] !== $filter->powerBands) {
            $this->whereAttribute($queryBuilder, $queryNameGenerator, $rootAlias, self::ATTRIBUTE_POWER, static function (string $value) use ($queryBuilder, $queryNameGenerator, $filter): string {
                $bands = [];
                foreach ($filter->powerBands as $band) {
                    $bounds = CatalogFilter::powerBandBounds($band);
                    $conditions = [];
                    foreach ([['min', $bounds['minIncluded'] ? '>=' : '>'], ['max', $bounds['maxIncluded'] ? '<=' : '<']] as [$bound, $operator]) {
                        if (null !== $bounds[$bound]) {
                            $parameter = $queryNameGenerator->generateParameterName('yackPower');
                            $queryBuilder->setParameter($parameter, $bounds[$bound]);
                            $conditions[] = sprintf('%s.float %s :%s', $value, $operator, $parameter);
                        }
                    }
                    $bands[] = '(' . implode(' AND ', $conditions) . ')';
                }

                return '(' . implode(' OR ', $bands) . ')';
            });
        }
    }

    /**
     * EXISTS an attribute value of the product with this code, matching the condition built on its
     * alias (`$condition` sets its own parameters on the query).
     *
     * @param \Closure(string): string $condition
     */
    private function whereAttribute(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $rootAlias,
        string $code,
        \Closure $condition,
    ): void {
        $valueAlias = $queryNameGenerator->generateJoinAlias('yackAttributeValue');
        $attributeAlias = $queryNameGenerator->generateJoinAlias('yackAttribute');
        $codeParameter = $queryNameGenerator->generateParameterName('yackAttributeCode');

        $subQuery = $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select($valueAlias . '.id')
            ->from($this->attributeValueClass, $valueAlias)
            ->innerJoin($valueAlias . '.attribute', $attributeAlias)
            ->andWhere(sprintf('%s.subject = %s', $valueAlias, $rootAlias))
            ->andWhere(sprintf('%s.code = :%s', $attributeAlias, $codeParameter))
            ->andWhere($condition($valueAlias))
        ;

        $queryBuilder
            ->andWhere($queryBuilder->expr()->exists($subQuery->getDQL()))
            ->setParameter($codeParameter, $code)
        ;
    }
}
