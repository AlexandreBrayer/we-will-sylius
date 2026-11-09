<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

use App\Entity\Content\CmsMedia;
use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaFiles;
use App\Yack\Content\Domain\CmsMediaLibrary;
use App\Yack\Content\Domain\CmsMediaRefused;

/**
 * Adds an image to the media library: content checked (CmsMediaFile::read(), type read with finfo),
 * file stored under a random name (CmsMediaFiles), then the image recorded with the cleaned name sent.
 * When the record fails, the stored file is deleted: no orphan file.
 */
final readonly class UploadCmsMedia
{
    public function __construct(
        private CmsMediaLibrary $library,
        private CmsMediaFiles $files,
    ) {
    }

    /**
     * @throws CmsMediaRefused
     */
    public function __invoke(string $bytes, string $originalName, string $alt = '', ?string $authorName = null): CmsMedia
    {
        $file = CmsMediaFile::read($bytes);
        $alt = CmsMediaFile::cleanAlt($alt);

        $path = $this->files->store($file);

        try {
            $media = new CmsMedia($path, $originalName, $file, $alt, $authorName);
            $this->library->add($media);
        } catch (\Throwable $failure) {
            $this->files->delete($path);

            throw $failure;
        }

        return $media;
    }
}
