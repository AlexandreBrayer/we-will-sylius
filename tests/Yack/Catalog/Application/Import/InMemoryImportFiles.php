<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application\Import;

use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFiles;
use App\Yack\Catalog\Domain\Import\ImportFormat;

/** Hand-written fake of the port: the files stored in memory, the tokens looked up and deleted. */
final class InMemoryImportFiles implements ImportFiles
{
    /** @var array<string, ImportFile> */
    public array $files = [];

    /** @var list<string> */
    public array $found = [];

    /** @var list<string> */
    public array $deleted = [];

    public function store(string $uploadedPath, string $originalName): ImportFile
    {
        $file = new ImportFile(md5($uploadedPath), $uploadedPath, $originalName, ImportFormat::Csv, 10);
        $this->files[$file->token] = $file;

        return $file;
    }

    public function find(string $token): ?ImportFile
    {
        $this->found[] = $token;

        return $this->files[$token] ?? null;
    }

    public function delete(ImportFile $file): void
    {
        $this->deleted[] = $file->token;
        unset($this->files[$file->token]);
    }
}
