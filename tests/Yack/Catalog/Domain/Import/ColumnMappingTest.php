<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\Import\ColumnMapping;
use App\Yack\Catalog\Domain\Import\ImportColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColumnMapping::class)]
final class ColumnMappingTest extends TestCase
{
    public function testHeadersAreMatchedToTheColumnsAnUnknownOneIgnoredARepeatedOneReadOnce(): void
    {
        $mapping = ColumnMapping::detect(['SKU', 'Couleur', 'Libellé', 'Code', 'Prix HT (€)']);

        $this->assertSame([0 => ImportColumn::Code, 2 => ImportColumn::Name, 4 => ImportColumn::Price], $mapping->columns);
        $this->assertSame([
            ['header' => 'SKU', 'column' => ImportColumn::Code],
            ['header' => 'Couleur', 'column' => null],
            ['header' => 'Libellé', 'column' => ImportColumn::Name],
            ['header' => 'Code', 'column' => null],
            ['header' => 'Prix HT (€)', 'column' => ImportColumn::Price],
        ], $mapping->pairs());
        $this->assertSame([ImportColumn::Kind, ImportColumn::Active, ImportColumn::Category, ImportColumn::Stock], $mapping->missing());
        $this->assertSame(ImportColumn::Brand, $mapping->absent()[0]);
        $this->assertCount(13, $mapping->absent());
    }

    public function testALineBecomesARecordOfTheFeedInEuros(): void
    {
        $mapping = ColumnMapping::detect(['Code', 'Nom', 'Prix HT', 'Marque']);

        $line = $mapping->line(['SRK25ZS-W', 'Console', 'douze']);

        $this->assertSame(['price' => ['currency' => 'EUR', 'amount' => 'douze'], 'sku' => 'SRK25ZS-W', 'label' => 'Console'], $line->record);
        $this->assertSame(['colonne « Prix HT » : nombre attendu (« douze »).'], $line->errors);
    }

    public function testAFileWithEveryColumnMissesNone(): void
    {
        $mapping = ColumnMapping::detect(array_map(static fn (ImportColumn $column): string => $column->header(), ImportColumn::cases()));

        $this->assertSame([[], []], [$mapping->missing(), $mapping->absent()]);
    }
}
