<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use App\Yack\Catalog\Domain\SyncSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ImportFormat::class)]
final class ImportFormatTest extends TestCase
{
    #[DataProvider('provideFiles')]
    public function testTheFormatIsRecognisedByTheExtensionAndCheckedOnTheContent(string $name, string $contentType, ImportFormat $format): void
    {
        $this->assertSame($format, ImportFormat::of($name, $contentType));
    }

    /** @return \Iterator<string, array{string, string, ImportFormat}> */
    public static function provideFiles(): \Iterator
    {
        yield 'csv' => ['tarifs.csv', 'text/plain', ImportFormat::Csv];
        yield 'extension en majuscules' => ['TARIFS.CSV', 'text/csv', ImportFormat::Csv];
        yield 'xlsx' => ['catalogue.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', ImportFormat::Xlsx];
        yield 'xlsx vu comme une archive' => ['catalogue.xlsx', 'application/zip', ImportFormat::Xlsx];
    }

    #[DataProvider('provideRefusedFiles')]
    public function testAnotherFormatOrAContentThatDoesNotMatchIsRefused(string $name, string $contentType, string $message): void
    {
        $this->expectException(ImportFileRefused::class);
        $this->expectExceptionMessage($message);

        ImportFormat::of($name, $contentType);
    }

    /** @return \Iterator<string, array{string, string, string}> */
    public static function provideRefusedFiles(): \Iterator
    {
        yield 'extension inconnue' => ['catalogue.xls', 'application/vnd.ms-excel', 'Format non accepté : fichier CSV ou XLSX attendu.'];
        yield 'sans extension' => ['catalogue', 'text/plain', 'Format non accepté : fichier CSV ou XLSX attendu.'];
        yield 'exécutable renommé' => ['tarifs.csv', 'application/x-executable', 'Le contenu du fichier ne correspond pas à un fichier CSV.'];
        yield 'texte renommé en xlsx' => ['tarifs.xlsx', 'text/plain', 'Le contenu du fichier ne correspond pas à un fichier XLSX.'];
    }

    public function testLabelSourceAndTypeSentToTheBrowser(): void
    {
        $this->assertSame(
            [['CSV', SyncSource::Csv, 'text/csv; charset=UTF-8'], ['XLSX', SyncSource::Xlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']],
            array_map(static fn (ImportFormat $format): array => [$format->label(), $format->source(), $format->contentType()], ImportFormat::cases()),
        );
    }
}
