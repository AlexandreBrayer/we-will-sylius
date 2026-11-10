<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

/**
 * A file sent for a manual import, kept outside the public directory under a random name (`token`)
 * between the preview and the confirmation, deleted once the import is done or cancelled.
 */
final readonly class ImportFile
{
    /** Largest file accepted. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Most lines read in a file, header aside. */
    public const MAX_ROWS = 5000;

    public function __construct(
        public string $token,
        public string $path,
        public string $originalName,
        public ImportFormat $format,
        public int $size,
    ) {
        if (!self::isToken($token)) {
            throw new \InvalidArgumentException(sprintf('Invalid import token "%s".', $token));
        }
    }

    /** Token of a stored file: 32 hexadecimal characters, nothing that could leave the storage directory. */
    public static function isToken(string $token): bool
    {
        return 1 === preg_match('/^[a-f0-9]{32}$/', $token);
    }

    /**
     * @throws ImportFileRefused
     */
    public static function checkSize(int $size): void
    {
        if ($size <= 0) {
            throw new ImportFileRefused('Le fichier est vide.');
        }
        if ($size > self::MAX_BYTES) {
            throw new ImportFileRefused(sprintf('Fichier trop volumineux (%d Mo maximum).', intdiv(self::MAX_BYTES, 1024 * 1024)));
        }
    }
}
