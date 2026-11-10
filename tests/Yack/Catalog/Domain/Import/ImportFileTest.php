<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ImportFile::class)]
final class ImportFileTest extends TestCase
{
    public function testAStoredFileIsKnownByItsToken(): void
    {
        $file = new ImportFile(str_repeat('a1', 16), '/var/import/a1.csv', 'tarifs.csv', ImportFormat::Csv, 120);

        $this->assertSame([str_repeat('a1', 16), '/var/import/a1.csv', 'tarifs.csv', ImportFormat::Csv, 120], [$file->token, $file->path, $file->originalName, $file->format, $file->size]);
    }

    #[DataProvider('provideTokens')]
    public function testATokenIsThirtyTwoHexadecimalCharacters(string $token, bool $valid): void
    {
        $this->assertSame($valid, ImportFile::isToken($token));
    }

    /** @return \Iterator<string, array{string, bool}> */
    public static function provideTokens(): \Iterator
    {
        yield 'valide' => [str_repeat('0f', 16), true];
        yield 'trop court' => ['0f0f', false];
        yield 'majuscules' => [str_repeat('0F', 16), false];
        yield 'chemin' => ['../../../../etc/passwd0000000000', false];
    }

    public function testAMalformedTokenIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid import token "../x".');

        new ImportFile('../x', '/tmp/x', 'x.csv', ImportFormat::Csv, 1);
    }

    #[DataProvider('provideRefusedSizes')]
    public function testAnEmptyOrTooLargeFileIsRefused(int $size, string $message): void
    {
        $this->expectException(ImportFileRefused::class);
        $this->expectExceptionMessage($message);

        ImportFile::checkSize($size);
    }

    /** @return \Iterator<string, array{int, string}> */
    public static function provideRefusedSizes(): \Iterator
    {
        yield 'vide' => [0, 'Le fichier est vide.'];
        yield 'trop volumineux' => [ImportFile::MAX_BYTES + 1, 'Fichier trop volumineux (5 Mo maximum).'];
    }

    public function testAFileUpToTheLimitIsTaken(): void
    {
        ImportFile::checkSize(ImportFile::MAX_BYTES);

        $this->addToAssertionCount(1);
    }
}
