<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Import;

use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use App\Yack\Catalog\Infrastructure\Import\SpreadsheetFileReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Real files written in a temporary directory, XLSX ones with OpenSpout.
 *
 * @internal
 */
#[CoversClass(SpreadsheetFileReader::class)]
final class SpreadsheetFileReaderTest extends TestCase
{
    private string $path;

    #[\Override]
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/yack-import-' . bin2hex(random_bytes(6));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testACsvInUtf8WithBomIsReadLineByLineEmptyLinesAside(): void
    {
        file_put_contents($this->path, "\xEF\xBB\xBF\r\n Code ;Désignation;Prix HT\r\nSRK25ZS-W;\"Console\r\nmurale\";\"1 699,90\"\r\n;;\r\nYK-2;Pompe;594,90\r\n");

        $rows = (new SpreadsheetFileReader())->read($this->csv());

        $this->assertSame(['Code', 'Désignation', 'Prix HT'], $rows->headers);
        $this->assertSame([3 => ['SRK25ZS-W', "Console\r\nmurale", '1 699,90'], 5 => ['YK-2', 'Pompe', '594,90']], $rows->rows);
        $this->assertSame(['UTF-8', ';'], [$rows->encoding, $rows->delimiter]);
    }

    public function testACsvExportedInWindows1252IsConvertedToUtf8(): void
    {
        file_put_contents($this->path, mb_convert_encoding("Code;Désignation\nYK-1;Pièce détachée €\n", 'Windows-1252', 'UTF-8'));

        $rows = (new SpreadsheetFileReader())->read($this->csv());

        $this->assertSame([['Code', 'Désignation'], [2 => ['YK-1', 'Pièce détachée €']], 'Windows-1252'], [$rows->headers, $rows->rows, $rows->encoding]);
    }

    #[DataProvider('provideDelimiters')]
    public function testTheSeparatorIsTheOneTheHeaderLineUsesMost(string $content, string $delimiter, array $firstLine): void
    {
        file_put_contents($this->path, $content);

        $rows = (new SpreadsheetFileReader())->read($this->csv());

        $this->assertSame([$delimiter, $firstLine], [$rows->delimiter, $rows->rows[2]]);
    }

    /** @return \Iterator<string, array{string, string, list<string>}> */
    public static function provideDelimiters(): \Iterator
    {
        yield 'virgule' => ["Code,Nom,Prix\nYK-1,Pompe,\"10,5\"\n", ',', ['YK-1', 'Pompe', '10,5']];
        yield 'tabulation' => ["Code\tNom\nYK-1\tPompe; chaude\n", "\t", ['YK-1', 'Pompe; chaude']];
        yield 'virgules plus nombreuses' => ["Code;Nom,court,long\nYK-1;a,b,c\n", ',', ['YK-1;a', 'b', 'c']];
        yield 'aucun séparateur' => ["Code\nYK-1\n", ';', ['YK-1']];
    }

    #[DataProvider('provideRefusedCsv')]
    public function testACsvWithoutLineOrWithTooManyLinesIsRefused(string $content, string $message): void
    {
        file_put_contents($this->path, $content);

        $this->expectException(ImportFileRefused::class);
        $this->expectExceptionMessage($message);

        (new SpreadsheetFileReader())->read($this->csv());
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function provideRefusedCsv(): \Iterator
    {
        yield 'vide' => ['', 'Le fichier ne contient aucune ligne : la première ligne doit porter les en-têtes de colonnes.'];
        yield 'lignes vides' => ["\n ; \n;;\n", 'Le fichier ne contient aucune ligne : la première ligne doit porter les en-têtes de colonnes.'];
        yield 'trop de lignes' => ["Code\n" . str_repeat("YK-1\n", ImportFile::MAX_ROWS + 1), 'Trop de lignes : 5000 au maximum par fichier.'];
    }

    public function testAFileUpToTheMaximumOfLinesIsRead(): void
    {
        file_put_contents($this->path, "Code\n" . str_repeat("YK-1\n", ImportFile::MAX_ROWS));

        $this->assertCount(ImportFile::MAX_ROWS, (new SpreadsheetFileReader())->read($this->csv())->rows);
    }

    public function testAMissingCsvCannotBeRead(): void
    {
        $this->expectException(ImportFileRefused::class);
        $this->expectExceptionMessage('Le fichier ne peut pas être lu.');

        (new SpreadsheetFileReader())->read($this->csv());
    }

    public function testTheCellsOfTheFirstSheetOfAnXlsxAreReadAsTyped(): void
    {
        $this->writeXlsx([
            Row::fromValues([]),
            Row::fromValues([' Code ', 'Prix HT', 'Stock', 'Actif', 'Puissance', 'Mise en vente', 'Garantie', 'Vide']),
            Row::fromValuesWithStyles(['SRK25ZS-W', 594.9, 12, true, 3.0, new \DateTimeImmutable('2026-11-10'), new \DateInterval('P2Y'), null], null, [5 => (new Style())->setFormat('dd/mm/yyyy')]),
            Row::fromValues(['', '']),
            Row::fromValues(['YK-2', 1e16, -4, false]),
        ], [Row::fromValues(['Autre feuille'])]);

        $rows = (new SpreadsheetFileReader())->read($this->xlsx());

        $this->assertSame(['Code', 'Prix HT', 'Stock', 'Actif', 'Puissance', 'Mise en vente', 'Garantie', 'Vide'], $rows->headers);
        $this->assertSame(['SRK25ZS-W', '594.9', '12', 'oui', '3', '10/11/2026'], array_slice($rows->rows[3], 0, 6));
        $this->assertSame(['YK-2', '10000000000000000', '-4', 'non'], $rows->rows[5]);
        $this->assertSame([null, null], [$rows->encoding, $rows->delimiter]);
    }

    public function testAnXlsxWithTooManyLinesIsRefused(): void
    {
        $this->writeXlsx(array_map(static fn (int $line): Row => Row::fromValues(['YK-' . $line]), range(1, ImportFile::MAX_ROWS + 3)));

        $this->expectException(ImportFileRefused::class);
        $this->expectExceptionMessage('Trop de lignes : 5000 au maximum par fichier.');

        (new SpreadsheetFileReader())->read($this->xlsx());
    }

    public function testAFileThatIsNotAnXlsxIsRefused(): void
    {
        file_put_contents($this->path, "Code;Nom\nYK-1;Pompe\n");

        $this->expectException(ImportFileRefused::class);
        $this->expectExceptionMessage('Le fichier XLSX ne peut pas être lu : est-il bien enregistré au format Excel (.xlsx) ?');

        (new SpreadsheetFileReader())->read($this->xlsx());
    }

    /**
     * @param list<Row> $rows
     * @param list<Row> $secondSheet
     */
    private function writeXlsx(array $rows, array $secondSheet = []): void
    {
        $writer = new Writer();
        $writer->openToFile($this->path);
        $writer->addRows($rows);
        if ([] !== $secondSheet) {
            $writer->addNewSheetAndMakeItCurrent();
            $writer->addRows($secondSheet);
        }
        $writer->close();
    }

    private function csv(): ImportFile
    {
        return new ImportFile(str_repeat('ab', 16), $this->path, 'tarifs.csv', ImportFormat::Csv, 10);
    }

    private function xlsx(): ImportFile
    {
        return new ImportFile(str_repeat('ab', 16), $this->path, 'catalogue.xlsx', ImportFormat::Xlsx, 10);
    }
}
