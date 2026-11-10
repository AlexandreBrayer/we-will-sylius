<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Import;

use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use App\Yack\Catalog\Infrastructure\Import\ImportTemplates;
use App\Yack\Catalog\Infrastructure\Import\SpreadsheetFileReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The templates are read back with the reader of the import: what is downloaded imports as is.
 *
 * @internal
 */
#[CoversClass(ImportTemplates::class)]
final class ImportTemplatesTest extends TestCase
{
    public function testTheCsvTemplateIsInUtf8WithBomAndSemicolons(): void
    {
        $content = (new ImportTemplates())->content(ImportFormat::Csv);

        $this->assertStringStartsWith("\xEF\xBB\xBFCode;Nom;Nature;Actif;", $content);
    }

    #[DataProvider('provideFormats')]
    public function testEachTemplateHoldsTheHeadersAndTheExampleLine(ImportFormat $format): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'yack-template-test');
        file_put_contents($path, (new ImportTemplates())->content($format));

        try {
            $rows = (new SpreadsheetFileReader())->read(new ImportFile(str_repeat('ab', 16), $path, 'modele', $format, 10));
        } finally {
            unlink($path);
        }

        $this->assertSame(array_map(static fn (ImportColumn $column): string => $column->header(), ImportColumn::cases()), $rows->headers);
        $this->assertSame([2 => array_map(static fn (ImportColumn $column): string => $column->example(), ImportColumn::cases())], $rows->rows);
    }

    /** @return \Iterator<string, array{ImportFormat}> */
    public static function provideFormats(): \Iterator
    {
        yield 'csv' => [ImportFormat::Csv];
        yield 'xlsx' => [ImportFormat::Xlsx];
    }

    public function testTheFileNameOfEachTemplate(): void
    {
        $this->assertSame(['modele-import-catalogue.csv', 'modele-import-catalogue.xlsx'], [ImportTemplates::fileName(ImportFormat::Csv), ImportTemplates::fileName(ImportFormat::Xlsx)]);
    }
}
