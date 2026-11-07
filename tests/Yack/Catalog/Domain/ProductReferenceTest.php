<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\ProductReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ProductReference::class)]
final class ProductReferenceTest extends TestCase
{
    /** @param list<string> $expected */
    #[DataProvider('printedReferences')]
    public function testCandidatesOfAPrintedReference(string $printed, array $expected): void
    {
        $this->assertSame($expected, ProductReference::candidates($printed));
    }

    /** @return \Iterator<string, array{string, list<string>}> */
    public static function printedReferences(): \Iterator
    {
        yield 'as printed' => ['SRK25ZS-W', ['SRK25ZS-W']];
        yield 'lower case, spaces around' => ['  srk25zs-w ', ['SRK25ZS-W']];
        yield 'typographic dash and spaces' => ['SRK25ZS – W', ['SRK25ZS-W']];
        yield 'space read instead of a dash' => ['SRK25ZS W', ['SRK25ZSW', 'SRK25ZS-W']];
        yield 'letters only' => ['FDCW-VNX-A', ['FDCW-VNX-A']];
        yield 'digits only' => ['123456', []];
        yield 'too short' => ['R3', []];
        yield 'empty' => ['  ', []];
        yield 'sentence' => ['Unité intérieure de climatiseur réversible mural', []];
        yield 'too long' => [str_repeat('A1', 21), []];
    }

    public function testDocumentsFromTheAttributeValue(): void
    {
        $this->assertSame(
            ['Documentation produit', 'Notice diagnostic', 'DAP & DWG & BIM'],
            ProductReference::documentsFrom('Documentation produit, Notice diagnostic, , documentation produit, DAP & DWG & BIM'),
        );
    }

    public function testNoDocumentsWithoutTextValue(): void
    {
        $this->assertSame([], ProductReference::documentsFrom(null));
        $this->assertSame([], ProductReference::documentsFrom(''));
        $this->assertSame([], ProductReference::documentsFrom(12));
    }

    public function testCarriesWhatTheProductSheetNeeds(): void
    {
        $reference = new ProductReference('SRK25ZS-W', 'SRK25ZS-W Mural - 2,5kW - R32', 'srk25zs-w-mural-2-5kw-r32-srk25zs-w', 'MHI', ['Notice diagnostic']);

        $this->assertSame('SRK25ZS-W', $reference->code);
        $this->assertSame('srk25zs-w-mural-2-5kw-r32-srk25zs-w', $reference->slug);
        $this->assertSame('MHI', $reference->brand);
        $this->assertSame(['Notice diagnostic'], $reference->documents);
    }
}
