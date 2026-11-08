<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Files of the media library (docs/adr/0005-fichiers-televerses.md): stored under CmsMediaFile::DIRECTORY
 * of the public dir with a random name, never a name or a path given by the client.
 */
interface CmsMediaFiles
{
    /**
     * @return string path relative to the public dir, matching CmsMediaFile::PATH_PATTERN
     */
    public function store(CmsMediaFile $file): string;

    /** Deletes an uploaded file; any other path (historical visual, unexpected value) is left alone. */
    public function delete(string $path): void;
}
