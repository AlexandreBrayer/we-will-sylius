<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Meilisearch;

use App\Yack\Catalog\Domain\CategoryDocument;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SearchDocument;
use App\Yack\Catalog\Infrastructure\Meilisearch\MeilisearchIndexes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MeilisearchIndexes::class)]
final class MeilisearchIndexesTest extends TestCase
{
    public function testIndexNames(): void
    {
        $this->assertSame('products', (new MeilisearchIndexes())->products());
        $this->assertSame('test_categories', (new MeilisearchIndexes('test_'))->categories());
    }

    public function testProductSettings(): void
    {
        $settings = MeilisearchIndexes::productSettings();

        $this->assertSame(['code', 'manufacturer_reference', 'name', 'brand', 'range', 'typology', 'category_path', 'short_description'], $settings['searchableAttributes']);
        $this->assertSame(['kind', 'enabled', 'category_codes', 'brand'], $settings['filterableAttributes']);
        // The attribute where a word is found (reference first) ranks before the exactness of the match.
        $this->assertSame(['words', 'typo', 'attribute', 'exactness', 'proximity', 'sort'], $settings['rankingRules']);
        // A reference is never matched with a typo: SRK25 does not find SRK35.
        $this->assertSame(['code', 'manufacturer_reference'], $settings['typoTolerance']['disableOnAttributes'] ?? null);
        $this->assertSame(['pompe à chaleur', 'pompes à chaleur'], $settings['synonyms']['pac'] ?? null);
    }

    public function testNoPriceIsIndexed(): void
    {
        $fields = array_merge(
            MeilisearchIndexes::PRODUCT_SEARCHABLE,
            MeilisearchIndexes::PRODUCT_DISPLAYED,
            array_keys(MeilisearchIndexes::productDocument(new SearchDocument('YK-PAE-M08', 'Evolys 8 kW', 'evolys-8-kw', true))),
        );

        $this->assertSame([], array_values(array_filter($fields, static fn (string $field): bool => 1 === preg_match('/pri[cx]/i', $field))));
    }

    public function testCategorySettings(): void
    {
        $settings = MeilisearchIndexes::categorySettings();

        $this->assertSame(['name', 'path'], $settings['searchableAttributes']);
        $this->assertSame(['code', 'name', 'slug'], $settings['displayedAttributes']);
        $this->assertSame(['kind', 'ancestor_codes'], $settings['filterableAttributes']);
        $this->assertSame(MeilisearchIndexes::synonyms(), $settings['synonyms']);
    }

    public function testSynonymsAreMutual(): void
    {
        $synonyms = MeilisearchIndexes::synonyms();

        $this->assertSame(['vrf', 'vrv'], $synonyms['drv']);
        $this->assertSame(['drv', 'vrv'], $synonyms['vrf']);
        $this->assertSame(['ecs'], $synonyms['eau chaude sanitaire']);
    }

    public function testProductDocument(): void
    {
        $document = MeilisearchIndexes::productDocument(new SearchDocument(
            code: 'SRK25ZSP-W1',
            name: 'Mural Premium 2,5 kW R32',
            slug: 'mural-premium-2-5-kw-r32-srk25zsp-w1',
            enabled: true,
            kind: ProductKind::Finished,
            brand: 'MHI',
            range: 'Premium',
            typology: 'Produit fini',
            manufacturerReference: 'SRK25ZSP-W',
            categoryCodes: ['clim_murale', 'climatisation', 'clik_yack'],
            categoryPath: ['Clik&Yack', 'Climatisation', 'Climatiseurs muraux'],
            shortDescription: '<p>Mural <strong>R32</strong> silencieux.</p> ',
            imagePath: 'ab/cd/mural.png',
        ));

        $this->assertSame([
            'id' => 'SRK25ZSP-W1',
            'code' => 'SRK25ZSP-W1',
            'manufacturer_reference' => 'SRK25ZSP-W',
            'name' => 'Mural Premium 2,5 kW R32',
            'brand' => 'MHI',
            'range' => 'Premium',
            'typology' => 'Produit fini',
            'category_path' => ['Clik&Yack', 'Climatisation', 'Climatiseurs muraux'],
            'category_codes' => ['clim_murale', 'climatisation', 'clik_yack'],
            'short_description' => 'Mural R32 silencieux.',
            'slug' => 'mural-premium-2-5-kw-r32-srk25zsp-w1',
            'image' => 'ab/cd/mural.png',
            'kind' => 'finished',
            'enabled' => true,
        ], $document);
    }

    public function testAProductWithoutOptionalData(): void
    {
        $document = MeilisearchIndexes::productDocument(new SearchDocument('YK-OLD', 'Ancien', 'ancien', false));

        $this->assertNull($document['kind']);
        $this->assertNull($document['short_description']);
        $this->assertFalse($document['enabled']);
    }

    public function testCategoryDocument(): void
    {
        $this->assertSame([
            'id' => 'parts_commandes',
            'code' => 'parts_commandes',
            'name' => 'Commandes & régulation',
            'path' => ['Clik&Yack Parts'],
            'ancestor_codes' => ['clik_yack_parts'],
            'slug' => 'nos-produits/clik-yack-parts/commandes',
            'kind' => 'spare_part',
        ], MeilisearchIndexes::categoryDocument(new CategoryDocument('parts_commandes', 'Commandes & régulation', 'nos-produits/clik-yack-parts/commandes', ProductKind::SparePart, ['Clik&Yack Parts'], ['clik_yack_parts'])));
    }

    #[DataProvider('provideCodes')]
    public function testDocumentId(string $expected, string $code): void
    {
        $this->assertSame($expected, MeilisearchIndexes::documentId($code));
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideCodes(): \Iterator
    {
        yield 'reference' => ['SRK25ZSP-W1', 'SRK25ZSP-W1'];
        yield 'category code' => ['clim_murale', 'clim_murale'];
        yield 'dot and slash' => ['A_2eB_2fC', 'A.B/C'];
        yield 'space' => ['YK_20200', 'YK 200'];
    }
}
