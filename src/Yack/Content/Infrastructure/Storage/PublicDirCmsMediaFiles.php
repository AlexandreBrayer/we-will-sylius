<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Storage;

use App\Yack\Content\Domain\CmsMediaFile;
use App\Yack\Content\Domain\CmsMediaFiles;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Files of the media library in public/media/yack-cms/ (served by nginx under /media/), named
 * bin2hex(random_bytes(16)) plus the extension of the type read on the content. delete() only acts on
 * a path of that form, resolved inside that directory: a historical visual or any other value is left alone.
 */
#[AsAlias(CmsMediaFiles::class)]
final readonly class PublicDirCmsMediaFiles implements CmsMediaFiles
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
    ) {
    }

    public function store(CmsMediaFile $file): string
    {
        $directory = $this->publicDir . '/' . CmsMediaFile::DIRECTORY;
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Dossier des médias impossible à créer : %s.', $directory));
        }

        // Mode x: never overwrites an existing file (a second random name is tried in the unlikely case of a clash).
        $path = '';
        $handle = false;
        for ($attempt = 0; $attempt < 3 && false === $handle; ++$attempt) {
            $path = CmsMediaFile::DIRECTORY . '/' . bin2hex(random_bytes(16)) . '.' . $file->extension();
            $handle = @fopen($this->publicDir . '/' . $path, 'x');
        }

        if (false === $handle) {
            throw new \RuntimeException(sprintf('Fichier impossible à écrire dans %s.', $directory));
        }

        try {
            if (false === fwrite($handle, $file->bytes)) {
                throw new \RuntimeException(sprintf('Fichier impossible à écrire dans %s.', $directory));
            }
        } finally {
            fclose($handle);
        }
        @chmod($this->publicDir . '/' . $path, 0o664);

        return $path;
    }

    public function delete(string $path): void
    {
        if (!CmsMediaFile::isUploadedPath($path)) {
            return;
        }

        $directory = realpath($this->publicDir . '/' . CmsMediaFile::DIRECTORY);
        $file = realpath($this->publicDir . '/' . $path);
        if (false === $directory || false === $file || \dirname($file) !== $directory || !is_file($file)) {
            return;
        }

        @unlink($file);
    }
}
