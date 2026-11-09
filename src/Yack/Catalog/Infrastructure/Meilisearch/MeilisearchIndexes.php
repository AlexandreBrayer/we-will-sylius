<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\CategoryDocument;
use App\Yack\Catalog\Domain\SearchDocument;
use App\Yack\Catalog\Domain\SearchVocabulary;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The two Meilisearch indexes of the shop, `products` and `categories` (MEILISEARCH_INDEX_PREFIX
 * in front of the names when several environments share one server): settings and documents.
 *
 * Products: a reference is searched by its beginning without typo tolerance, the other texts
 * tolerate typos and know the trade synonyms (SearchVocabulary). The attribute where a word is
 * found ranks before the exactness of the match, so a match on the reference comes first. No
 * price is indexed.
 */
final readonly class MeilisearchIndexes
{
    public const PRODUCTS = 'products';
    public const CATEGORIES = 'categories';

    /** Searchable attributes of the products, by decreasing priority. */
    public const PRODUCT_SEARCHABLE = ['code', 'manufacturer_reference', 'name', 'brand', 'range', 'typology', 'category_path', 'short_description'];

    /** Attributes searched first for a query typed as a reference (SearchQuery::looksLikeReference()). */
    public const REFERENCE_SEARCHABLE = ['code', 'manufacturer_reference'];

    /** What a product suggestion shows. */
    public const PRODUCT_DISPLAYED = ['code', 'name', 'slug', 'brand', 'image'];

    public const CATEGORY_DISPLAYED = ['code', 'name', 'slug'];

    private const RANKING_RULES = ['words', 'typo', 'attribute', 'exactness', 'proximity', 'sort'];

    /** Words too common to tell products apart. */
    private const STOP_WORDS = ['de', 'du', 'des', 'la', 'le', 'les', 'l', 'd', 'au', 'aux', 'pour', 'et', 'en', 'un', 'une', 'avec', 'sur'];

    public function __construct(
        #[Autowire('%env(MEILISEARCH_INDEX_PREFIX)%')]
        private string $prefix = '',
    ) {
    }

    public function products(): string
    {
        return $this->prefix . self::PRODUCTS;
    }

    public function categories(): string
    {
        return $this->prefix . self::CATEGORIES;
    }

    /** @return array<string, mixed> */
    public static function productSettings(): array
    {
        return [
            'searchableAttributes' => self::PRODUCT_SEARCHABLE,
            'displayedAttributes' => self::PRODUCT_DISPLAYED,
            'filterableAttributes' => ['kind', 'enabled', 'category_codes', 'brand'],
            'sortableAttributes' => ['name'],
            'rankingRules' => self::RANKING_RULES,
            'stopWords' => self::STOP_WORDS,
            'synonyms' => self::synonyms(),
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 5, 'twoTypos' => 8],
                'disableOnAttributes' => ['code', 'manufacturer_reference'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function categorySettings(): array
    {
        return [
            'searchableAttributes' => ['name', 'path'],
            'displayedAttributes' => self::CATEGORY_DISPLAYED,
            'filterableAttributes' => ['kind'],
            'rankingRules' => self::RANKING_RULES,
            'stopWords' => self::STOP_WORDS,
            'synonyms' => self::synonyms(),
            'typoTolerance' => ['enabled' => true, 'minWordSizeForTypos' => ['oneTypo' => 5, 'twoTypos' => 8]],
        ];
    }

    /**
     * Mutual synonyms: each expression of a SearchVocabulary group leads to all the others.
     *
     * @return array<string, list<string>>
     */
    public static function synonyms(): array
    {
        $synonyms = [];
        foreach (SearchVocabulary::SYNONYMS as $group) {
            foreach ($group as $expression) {
                $synonyms[$expression] = array_values(array_diff($group, [$expression]));
            }
        }

        return $synonyms;
    }

    /** @return array<string, mixed> */
    public static function productDocument(SearchDocument $product): array
    {
        return [
            'id' => self::documentId($product->code),
            'code' => $product->code,
            'manufacturer_reference' => $product->manufacturerReference,
            'name' => $product->name,
            'brand' => $product->brand,
            'range' => $product->range,
            'typology' => $product->typology,
            'category_path' => $product->categoryPath,
            'category_codes' => $product->categoryCodes,
            'short_description' => null === $product->shortDescription ? null : trim(strip_tags($product->shortDescription)),
            'slug' => $product->slug,
            'image' => $product->imagePath,
            'kind' => $product->kind?->value,
            'enabled' => $product->enabled,
        ];
    }

    /** @return array<string, mixed> */
    public static function categoryDocument(CategoryDocument $category): array
    {
        return [
            'id' => self::documentId($category->code),
            'code' => $category->code,
            'name' => $category->name,
            'path' => $category->path,
            'slug' => $category->slug,
            'kind' => $category->kind->value,
        ];
    }

    /**
     * Meilisearch identifier of a code: letters, digits, "-" and "_" are kept, any other character
     * becomes "_" followed by its hexadecimal code ("A.B" gives "A_2eB").
     */
    public static function documentId(string $code): string
    {
        return (string) preg_replace_callback('/[^A-Za-z0-9_-]/', static fn (array $match): string => '_' . bin2hex($match[0]), $code);
    }
}
