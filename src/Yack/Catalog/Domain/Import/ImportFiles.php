<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

/** Port: private storage of the imported files, between the preview and the confirmation. */
interface ImportFiles
{
    /**
     * Keeps the file sent under a random name, after checking its size and its format on its content.
     *
     * @throws ImportFileRefused
     */
    public function store(string $uploadedPath, string $originalName): ImportFile;

    /** The stored file of this token, null when it is unknown or already deleted. */
    public function find(string $token): ?ImportFile;

    public function delete(ImportFile $file): void;
}
