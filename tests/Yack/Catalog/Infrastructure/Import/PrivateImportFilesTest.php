<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Import;

use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use App\Yack\Catalog\Infrastructure\Import\ImportTemplates;
use App\Yack\Catalog\Infrastructure\Import\PrivateImportFiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Real files in a temporary directory.
 *
 * @internal
 */
#[CoversClass(PrivateImportFiles::class)]
final class PrivateImportFilesTest extends TestCase
{
    private string $directory;

    private string $upload;

    private PrivateImportFiles $files;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/yack-private-' . bin2hex(random_bytes(6)) . '/catalog-import';
        $this->upload = (string) tempnam(sys_get_temp_dir(), 'yack-upload');
        $this->files = new PrivateImportFiles($this->directory . '/');
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
            rmdir(dirname($this->directory));
        }
        if (is_file($this->upload)) {
            unlink($this->upload);
        }
    }

    public function testAStoredCsvIsKeptUnderItsTokenAndFoundBack(): void
    {
        file_put_contents($this->upload, "Code;Nom\nYK-1;Pompe\n");

        $file = $this->files->store($this->upload, 'C:\\Exports\\tarifs.csv');

        $this->assertTrue(ImportFile::isToken($file->token));
        $this->assertSame([$this->directory . '/' . $file->token . '.csv', 'tarifs.csv', ImportFormat::Csv, 20], [$file->path, $file->originalName, $file->format, $file->size]);
        $this->assertSame("Code;Nom\nYK-1;Pompe\n", file_get_contents($file->path));
        $found = $this->files->find($file->token);
        $this->assertSame([$file->token, $file->path, 'tarifs.csv', ImportFormat::Csv, 20], [$found?->token, $found?->path, $found?->originalName, $found?->format, $found?->size]);
    }

    public function testAStoredXlsxIsRecognisedOnItsContent(): void
    {
        file_put_contents($this->upload, (new ImportTemplates())->content(ImportFormat::Xlsx));

        $file = $this->files->store($this->upload, 'catalogue.xlsx');

        $this->assertSame([ImportFormat::Xlsx, $this->directory . '/' . $file->token . '.xlsx'], [$file->format, $file->path]);
    }

    public function testADeletedFileIsNoLongerFound(): void
    {
        file_put_contents($this->upload, "Code\nYK-1\n");
        $file = $this->files->store($this->upload, 'tarifs.csv');

        $this->files->delete($file);
        $this->files->delete($file);

        $this->assertNull($this->files->find($file->token));
        $this->assertSame([], glob($this->directory . '/*'));
    }

    public function testTheFilesLeftBehindForMoreThanADayAreDeletedOnTheNextStore(): void
    {
        file_put_contents($this->upload, "Code\nYK-1\n");
        $old = $this->files->store($this->upload, 'ancien.csv');
        $recent = $this->files->store($this->upload, 'recent.csv');
        touch($old->path, time() - 86401);
        touch($this->directory . '/' . $old->token . '.json', time() - 86401);

        $this->files->store($this->upload, 'nouveau.csv');

        $this->assertNull($this->files->find($old->token));
        $this->assertSame('recent.csv', $this->files->find($recent->token)?->originalName);
    }

    #[DataProvider('provideUnknownTokens')]
    public function testAnUnknownOrBrokenImportIsNotFound(string $token, ?string $note, bool $withFile): void
    {
        mkdir($this->directory, 0o770, true);
        if (null !== $note) {
            file_put_contents($this->directory . '/' . $token . '.json', $note);
        }
        if ($withFile) {
            file_put_contents($this->directory . '/' . $token . '.csv', "Code\n");
        }

        $this->assertNull($this->files->find($token));
    }

    /** @return \Iterator<string, array{string, string|null, bool}> */
    public static function provideUnknownTokens(): \Iterator
    {
        $token = str_repeat('cd', 16);

        yield 'jeton mal formé' => ['../../etc/passwd', null, false];
        yield 'sans note' => [$token, null, true];
        yield 'note illisible' => [$token, 'pas du json', true];
        yield 'format inconnu' => [$token, '{"name":"tarifs.ods","format":"ods"}', true];
        yield 'fichier disparu' => [$token, '{"name":"tarifs.csv","format":"csv"}', false];
    }

    public function testANoteWithoutNameGivesAnEmptyName(): void
    {
        $token = str_repeat('ef', 16);
        mkdir($this->directory, 0o770, true);
        file_put_contents($this->directory . '/' . $token . '.json', '{"format":"csv"}');
        file_put_contents($this->directory . '/' . $token . '.csv', "Code\n");

        $this->assertSame(['', 5], [$this->files->find($token)?->originalName, $this->files->find($token)?->size]);
    }

    #[DataProvider('provideNames')]
    public function testTheOriginalNameIsKeptWithoutFolderNorControlCharacter(string $originalName, string $name): void
    {
        file_put_contents($this->upload, "Code\nYK-1\n");

        $this->assertSame($name, $this->files->store($this->upload, $originalName)->originalName);
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function provideNames(): \Iterator
    {
        yield 'chemin' => ['../../var/tarifs.csv', 'tarifs.csv'];
        yield 'caractères de contrôle' => ["tarifs\u{0}\n.csv", 'tarifs.csv'];
        yield 'trop long' => [str_repeat('é', 200) . '.csv', str_repeat('é', 120)];
    }

    #[DataProvider('provideRefusedUploads')]
    public function testAnEmptyMissingOrMismatchingUploadIsRefused(?string $content, string $name, string $message): void
    {
        if (null === $content) {
            unlink($this->upload);
        } else {
            file_put_contents($this->upload, $content);
        }

        $this->expectException(ImportFileRefused::class);
        $this->expectExceptionMessage($message);

        $this->files->store($this->upload, $name);
    }

    /** @return \Iterator<string, array{string|null, string, string}> */
    public static function provideRefusedUploads(): \Iterator
    {
        yield 'vide' => ['', 'tarifs.csv', 'Le fichier est vide.'];
        yield 'absent' => [null, 'tarifs.csv', 'Le fichier est vide.'];
        yield 'csv renommé en xlsx' => ["Code;Nom\nYK-1;Pompe\n", 'tarifs.xlsx', 'Le contenu du fichier ne correspond pas à un fichier XLSX.'];
    }
}
