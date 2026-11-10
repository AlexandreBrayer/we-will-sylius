<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\Import\ImportedLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ImportedLine::class)]
final class ImportedLineTest extends TestCase
{
    public function testValidCellsAreWrittenIntoTheRecord(): void
    {
        $line = ImportedLine::fill(['sku' => 'OLD'], [[ImportColumn::Code, ' SRK25ZS-W '], [ImportColumn::Stock, '12']]);

        $this->assertSame(['sku' => 'SRK25ZS-W', 'stock' => 12], $line->record);
        $this->assertSame(['SRK25ZS-W', true, ''], [$line->code(), $line->isValid(), $line->reason()]);
    }

    public function testAnInvalidCellIsKeptAsTypedAndItsErrorCollected(): void
    {
        $line = ImportedLine::fill(['stock' => 3], [[ImportColumn::Stock, ' trois '], [ImportColumn::Code, ''], [ImportColumn::Active, 'peut-être']]);

        $this->assertSame(['stock' => 'trois', 'status' => 'peut-être'], $line->record);
        $this->assertFalse($line->isValid());
        $this->assertSame('colonne « Stock » : nombre attendu (« trois »). ; colonne « Code » vide. ; colonne « Actif » : oui ou non attendu (« peut-être »).', $line->reason());
        $this->assertNull($line->code());
    }

    public function testACodeThatIsNotATextIsNone(): void
    {
        $this->assertNull((new ImportedLine(['sku' => 12]))->code());
    }
}
