<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Import;

use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFiles;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter: imported files kept in var/yack/private/catalog-import, outside the public directory, under a
 * random name (the token) with a JSON note of the original name. The format is checked on the content
 * (fileinfo), never on what the browser declared. Files left behind (preview never confirmed) are
 * deleted after a day.
 */
#[AsAlias(ImportFiles::class)]
final readonly class PrivateImportFiles implements ImportFiles
{
    private const LIFETIME = 86400;

    private string $directory;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/yack/private/catalog-import')]
        string $directory,
    ) {
        $this->directory = rtrim($directory, '/');
    }

    public function store(string $uploadedPath, string $originalName): ImportFile
    {
        $size = is_file($uploadedPath) ? (int) filesize($uploadedPath) : 0;
        ImportFile::checkSize($size);
        $format = ImportFormat::of($originalName, (string) (new \finfo(\FILEINFO_MIME_TYPE))->file($uploadedPath));

        $this->purge();
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" cannot be created.', $this->directory));
        }

        $token = bin2hex(random_bytes(16));
        $path = $this->path($token, $format);
        if (!copy($uploadedPath, $path)) {
            throw new \RuntimeException(sprintf('Imported file cannot be stored in "%s".', $this->directory));
        }
        file_put_contents($this->note($token), json_encode(['name' => self::safeName($originalName), 'format' => $format->value], \JSON_THROW_ON_ERROR));

        return new ImportFile($token, $path, self::safeName($originalName), $format, $size);
    }

    public function find(string $token): ?ImportFile
    {
        if (!ImportFile::isToken($token) || !is_file($this->note($token))) {
            return null;
        }
        $note = json_decode((string) file_get_contents($this->note($token)), true);
        $format = ImportFormat::tryFrom(is_array($note) && is_string($note['format'] ?? null) ? $note['format'] : '');
        $name = is_array($note) && is_string($note['name'] ?? null) ? $note['name'] : '';
        if (null === $format || !is_file($path = $this->path($token, $format))) {
            return null;
        }

        return new ImportFile($token, $path, $name, $format, (int) filesize($path));
    }

    public function delete(ImportFile $file): void
    {
        foreach ([$this->path($file->token, $file->format), $this->note($file->token)] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** Files of imports never confirmed nor cancelled. */
    private function purge(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < time() - self::LIFETIME) {
                unlink($path);
            }
        }
    }

    private function path(string $token, ImportFormat $format): string
    {
        return sprintf('%s/%s.%s', $this->directory, $token, $format->value);
    }

    private function note(string $token): string
    {
        return sprintf('%s/%s.json', $this->directory, $token);
    }

    /** Original name as shown in the back-office: no folder, no control character, 120 characters at most. */
    private static function safeName(string $name): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name)));

        return mb_substr('' === $name ? 'import' : $name, 0, 120);
    }
}
