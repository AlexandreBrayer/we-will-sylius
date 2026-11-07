<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Doctrine;

use App\Yack\Catalog\Domain\CategorySuggestion;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductSearch;
use App\Yack\Catalog\Domain\ProductSuggestion;
use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchQuery;
use App\Yack\Catalog\Domain\SearchResult;
use App\Yack\Catalog\Domain\Suggestions;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter: database search, used when Meilisearch is not configured or does not answer.
 *
 * Each word of the query (or one of its trade synonyms) must be found, case and accents ignored
 * (collation of the database), in the French name, the code or the brand / range attributes of
 * an enabled product of the current channel. Order: exact code, code starting with the query,
 * name starting with the query, then the name in alphabetical order.
 */
final readonly class DoctrineProductSearch implements ProductSearch
{
    /** Text attributes searched besides the name and the code. */
    public const ATTRIBUTES = ['marque', 'gamme', 'reference_constructeur'];

    public const ATTRIBUTE_BRAND = 'marque';

    /** Roots of the shop menu branches whose categories are suggested. */
    private const CATEGORY_ROOTS = [ProductKind::Finished, ProductKind::SparePart];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChannelContextInterface $channelContext,
        #[Autowire('%sylius.model.product.class%')]
        private string $productClass,
        #[Autowire('%sylius.model.product_attribute_value.class%')]
        private string $attributeValueClass,
        #[Autowire('%sylius.model.product_image.class%')]
        private string $productImageClass,
        #[Autowire('%sylius.model.taxon.class%')]
        private string $taxonClass,
        #[Autowire('%app.yack.locale%')]
        private string $locale,
    ) {
    }

    public function search(SearchQuery $query): SearchResult
    {
        if ([] === $query->terms()) {
            return SearchResult::none(SearchEngine::Database);
        }

        $total = (int) $this->matching($query)->select('COUNT(product.id)')->getQuery()->getSingleScalarResult();
        if (0 === $total) {
            return SearchResult::none(SearchEngine::Database);
        }

        $rows = $this->ranked($query, 'product.code AS code')
            ->setFirstResult($query->offset())
            ->setMaxResults($query->perPage)
            ->getQuery()
            ->getScalarResult();

        return new SearchResult(array_values(array_map(static fn (array $row): string => (string) $row['code'], $rows)), $total, SearchEngine::Database);
    }

    public function suggest(string $text, int $productLimit, int $categoryLimit): Suggestions
    {
        $query = new SearchQuery($text, max(1, $productLimit));
        if ([] === $query->terms()) {
            return Suggestions::none(SearchEngine::Database);
        }

        return new Suggestions(
            $productLimit > 0 ? $this->suggestedProducts($query) : [],
            $categoryLimit > 0 ? $this->suggestedCategories($query, $categoryLimit) : [],
            SearchEngine::Database,
        );
    }

    /** @return list<ProductSuggestion> */
    private function suggestedProducts(SearchQuery $query): array
    {
        $rows = $this->ranked($query, 'product.id AS id', 'product.code AS code', 'translation.name AS name', 'translation.slug AS slug')
            ->setMaxResults($query->perPage)
            ->getQuery()
            ->getScalarResult();
        if ([] === $rows) {
            return [];
        }

        $ids = array_column($rows, 'id');
        $brands = $this->brands($ids);
        $images = $this->images($ids);

        return array_values(array_map(static fn (array $row): ProductSuggestion => new ProductSuggestion(
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['slug'],
            $brands[(string) $row['id']] ?? null,
            $images[(string) $row['id']] ?? null,
        ), $rows));
    }

    /** @return list<CategorySuggestion> */
    private function suggestedCategories(SearchQuery $query, int $limit): array
    {
        $branches = $this->entityManager->createQueryBuilder()
            ->select('branch.code AS code', 'IDENTITY(branch.root) AS root', 'branch.left AS lft', 'branch.right AS rgt')
            ->from($this->taxonClass, 'branch')
            ->andWhere('branch.code IN (:codes)')
            ->setParameter('codes', array_map(static fn (ProductKind $kind): string => $kind->catalogTaxonCode(), self::CATEGORY_ROOTS))
            ->getQuery()
            ->getScalarResult();
        if ([] === $branches) {
            return [];
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('taxon.code AS code', 'translation.name AS name', 'translation.slug AS slug')
            ->addSelect('CASE WHEN LOWER(translation.name) LIKE :namePrefix THEN 0 ELSE 1 END AS HIDDEN relevance')
            ->from($this->taxonClass, 'taxon')
            ->innerJoin('taxon.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->setParameter('locale', $this->locale)
            ->setParameter('namePrefix', SearchPatterns::startsWith($query->text))
            ->orderBy('relevance', 'ASC')
            ->addOrderBy('taxon.level', 'ASC')
            ->addOrderBy('translation.name', 'ASC')
            ->setMaxResults($limit);

        $inBranch = $queryBuilder->expr()->orX();
        foreach (array_values($branches) as $i => $branch) {
            $inBranch->add(sprintf('(IDENTITY(taxon.root) = :root%1$d AND taxon.left >= :left%1$d AND taxon.right <= :right%1$d)', $i));
            $queryBuilder
                ->setParameter('root' . $i, $branch['root'] ?? 0)
                ->setParameter('left' . $i, $branch['lft'])
                ->setParameter('right' . $i, $branch['rgt']);
        }
        $queryBuilder->andWhere($inBranch);

        foreach ($query->terms() as $i => $term) {
            $any = $queryBuilder->expr()->orX();
            foreach (SearchPatterns::alternatives($term) as $j => $pattern) {
                $any->add(sprintf('LOWER(translation.name) LIKE :category%d_%d', $i, $j));
                $queryBuilder->setParameter(sprintf('category%d_%d', $i, $j), $pattern);
            }
            $queryBuilder->andWhere($any);
        }

        return array_values(array_map(static fn (array $row): CategorySuggestion => new CategorySuggestion(
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['slug'],
        ), $queryBuilder->getQuery()->getScalarResult()));
    }

    /** Matching products, most relevant first, with the selected columns. */
    private function ranked(SearchQuery $query, string ...$columns): QueryBuilder
    {
        return $this->matching($query)
            ->select(...$columns)
            ->addSelect(
                'CASE WHEN LOWER(product.code) = :exactCode THEN 0'
                . ' WHEN LOWER(product.code) LIKE :codePrefix THEN 1'
                . ' WHEN LOWER(translation.name) LIKE :namePrefix THEN 2'
                . ' ELSE 3 END AS HIDDEN relevance',
            )
            ->setParameter('exactCode', mb_strtolower($query->text))
            ->setParameter('codePrefix', SearchPatterns::startsWith($query->text))
            ->setParameter('namePrefix', SearchPatterns::startsWith($query->text))
            ->orderBy('relevance', 'ASC')
            ->addOrderBy('translation.name', 'ASC')
            ->addOrderBy('product.code', 'ASC');
    }

    /** Enabled products of the channel holding every word of the query (or a synonym); the query has words. */
    private function matching(SearchQuery $query): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from($this->productClass, 'product')
            ->innerJoin('product.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('product.enabled = true')
            ->andWhere(':channel MEMBER OF product.channels')
            ->setParameter('locale', $this->locale)
            ->setParameter('channel', $this->channelContext->getChannel())
            ->setParameter('searchedAttributes', self::ATTRIBUTES);

        foreach ($query->terms() as $i => $term) {
            $any = $queryBuilder->expr()->orX();
            $attributeMatch = [];
            foreach (SearchPatterns::alternatives($term) as $j => $pattern) {
                $parameter = sprintf('term%d_%d', $i, $j);
                $any->add(sprintf('LOWER(translation.name) LIKE :%s', $parameter));
                $any->add(sprintf('LOWER(product.code) LIKE :%s', $parameter));
                $attributeMatch[] = sprintf('LOWER(value%d.text) LIKE :%s', $i, $parameter);
                $queryBuilder->setParameter($parameter, $pattern);
            }
            $any->add(sprintf(
                'EXISTS (SELECT value%1$d.id FROM %2$s value%1$d INNER JOIN value%1$d.attribute attribute%1$d'
                . ' WHERE value%1$d.subject = product AND attribute%1$d.code IN (:searchedAttributes) AND (%3$s))',
                $i,
                $this->attributeValueClass,
                implode(' OR ', $attributeMatch),
            ));
            $queryBuilder->andWhere($any);
        }

        if (null !== $query->kind) {
            $this->inBranch($queryBuilder, $query->kind);
        }

        return $queryBuilder;
    }

    /** Products whose main category is in the menu branch of this kind. */
    private function inBranch(QueryBuilder $queryBuilder, ProductKind $kind): void
    {
        $queryBuilder
            ->innerJoin('product.mainTaxon', 'mainTaxon')
            ->innerJoin($this->taxonClass, 'branch', 'WITH', 'branch.code = :branchCode')
            ->andWhere('IDENTITY(mainTaxon.root) = IDENTITY(branch.root)')
            ->andWhere('mainTaxon.left >= branch.left')
            ->andWhere('mainTaxon.right <= branch.right')
            ->setParameter('branchCode', $kind->catalogTaxonCode());
    }

    /**
     * @param list<mixed> $productIds
     *
     * @return array<string, string> product id => brand
     */
    private function brands(array $productIds): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(value.subject) AS productId', 'value.text AS brand')
            ->from($this->attributeValueClass, 'value')
            ->innerJoin('value.attribute', 'attribute')
            ->andWhere('attribute.code = :brand')
            ->andWhere('value.subject IN (:ids)')
            ->setParameter('brand', self::ATTRIBUTE_BRAND)
            ->setParameter('ids', $productIds)
            ->getQuery()
            ->getScalarResult();

        $brands = [];
        foreach ($rows as $row) {
            if (is_string($row['brand'] ?? null) && '' !== trim($row['brand'])) {
                $brands[(string) $row['productId']] = trim($row['brand']);
            }
        }

        return $brands;
    }

    /**
     * Main image of each product (type "main", else the first one).
     *
     * @param list<mixed> $productIds
     *
     * @return array<string, string> product id => image path
     */
    private function images(array $productIds): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(image.owner) AS productId', 'image.path AS path', 'image.type AS type')
            ->from($this->productImageClass, 'image')
            ->andWhere('image.owner IN (:ids)')
            ->setParameter('ids', $productIds)
            ->orderBy('image.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $images = [];
        foreach ($rows as $row) {
            $id = (string) $row['productId'];
            if (!is_string($row['path'] ?? null)) {
                continue;
            }
            if (!isset($images[$id]) || ('main' === $row['type'] && 'main' !== $images[$id]['type'])) {
                $images[$id] = ['path' => $row['path'], 'type' => $row['type']];
            }
        }

        return array_map(static fn (array $image): string => $image['path'], $images);
    }
}
