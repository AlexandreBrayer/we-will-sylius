<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\ProductKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ProductKind::class)]
final class ProductKindTest extends TestCase
{
    /**
     * @param list<string> $taxonCodes
     */
    #[DataProvider('provideTaxonCodes')]
    public function testFromTaxonCodes(?ProductKind $expected, array $taxonCodes): void
    {
        $this->assertSame($expected, ProductKind::fromTaxonCodes($taxonCodes));
    }

    /**
     * @return \Iterator<string, array{ProductKind|null, list<string>}>
     */
    public static function provideTaxonCodes(): \Iterator
    {
        yield 'spare part branch' => [ProductKind::SparePart, ['clik_yack_parts', 'parts_hydraulique']];
        yield 'spare part, nearest first' => [ProductKind::SparePart, ['parts_hydraulique', 'clik_yack_parts', 'menu']];
        yield 'finished product' => [ProductKind::Finished, ['pac_air_eau_yack_modulo_neo', 'pac_air_eau_yack', 'pac_air_eau', 'clik_yack']];
        yield 'brand only' => [null, ['marque_yack']];
        yield 'no category' => [null, []];
    }

    #[DataProvider('provideApiTypes')]
    public function testFromApiType(?ProductKind $expected, string $apiType): void
    {
        $this->assertSame($expected, ProductKind::fromApiType($apiType));
    }

    /**
     * @return \Iterator<string, array{ProductKind|null, string}>
     */
    public static function provideApiTypes(): \Iterator
    {
        yield 'part' => [ProductKind::SparePart, 'part'];
        yield 'product' => [ProductKind::Finished, 'product'];
        yield 'unknown' => [null, 'autre'];
        yield 'empty' => [null, ''];
    }

    public function testApiTypeRoundTrip(): void
    {
        $this->assertSame('product', ProductKind::Finished->apiType());
        $this->assertSame('part', ProductKind::SparePart->apiType());
        foreach (ProductKind::cases() as $kind) {
            $this->assertSame($kind, ProductKind::fromApiType($kind->apiType()));
        }
    }

    public function testLabelsAndCatalogTaxonCodes(): void
    {
        $this->assertSame('Produit fini', ProductKind::Finished->label());
        $this->assertSame('Pièce détachée', ProductKind::SparePart->label());
        $this->assertSame('clik_yack', ProductKind::Finished->catalogTaxonCode());
        $this->assertSame('clik_yack_parts', ProductKind::SparePart->catalogTaxonCode());
    }
}
