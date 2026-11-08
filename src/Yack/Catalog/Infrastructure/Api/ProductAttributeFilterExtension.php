<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Yack\Catalog\Domain\InvalidCatalogFilter;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Shop API products filtered on their attributes: `?yack_brand=Yack` ("marque"),
 * `?yack_range=Evolys` ("gamme"), `?yack_power_min=6&yack_power_max=9` ("puissance_kw", kW,
 * bounds included), `?yack_refrigerant=R32` ("fluide_frigorigene"). A power that is not a
 * number answers 400.
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
        foreach ([self::ATTRIBUTE_BRAND => $filter->brand, self::ATTRIBUTE_RANGE => $filter->range, self::ATTRIBUTE_REFRIGERANT => $filter->refrigerant] as $code => $value) {
            if (null !== $value) {
                $this->whereAttribute($queryBuilder, $queryNameGenerator, $rootAlias, $code, 'text', '=', $value);
            }
        }
        if (null !== $filter->powerMin) {
            $this->whereAttribute($queryBuilder, $queryNameGenerator, $rootAlias, self::ATTRIBUTE_POWER, 'float', '>=', $filter->powerMin);
        }
        if (null !== $filter->powerMax) {
            $this->whereAttribute($queryBuilder, $queryNameGenerator, $rootAlias, self::ATTRIBUTE_POWER, 'float', '<=', $filter->powerMax);
        }
    }

    /** EXISTS an attribute value of the product with this code and a matching value. */
    private function whereAttribute(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $rootAlias,
        string $code,
        string $field,
        string $operator,
        string|float $value,
    ): void {
        $valueAlias = $queryNameGenerator->generateJoinAlias('yackAttributeValue');
        $attributeAlias = $queryNameGenerator->generateJoinAlias('yackAttribute');
        $codeParameter = $queryNameGenerator->generateParameterName('yackAttributeCode');
        $valueParameter = $queryNameGenerator->generateParameterName('yackAttributeValue');

        $subQuery = $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select($valueAlias . '.id')
            ->from($this->attributeValueClass, $valueAlias)
            ->innerJoin($valueAlias . '.attribute', $attributeAlias)
            ->andWhere(sprintf('%s.subject = %s', $valueAlias, $rootAlias))
            ->andWhere(sprintf('%s.code = :%s', $attributeAlias, $codeParameter))
            ->andWhere(sprintf('%s.%s %s :%s', $valueAlias, $field, $operator, $valueParameter))
        ;

        $queryBuilder
            ->andWhere($queryBuilder->expr()->exists($subQuery->getDQL()))
            ->setParameter($codeParameter, $code)
            ->setParameter($valueParameter, $value)
        ;
    }
}
