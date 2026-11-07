<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * Photo of a rating plate, as uploaded: kept in memory for the reading only, never stored.
 * The media type is the one read on the content (finfo), never the file name or the Content-Type sent.
 */
final readonly class NameplatePhoto
{
    /** Same limit as nginx, adapter-node and the front check (docs/adr/0005-fichiers-televerses.md). */
    public const MAX_BYTES = 20 * 1024 * 1024;

    public const MEDIA_TYPES = ['image/jpeg', 'image/png'];

    private function __construct(
        public string $bytes,
        public string $mediaType,
    ) {
    }

    /** @throws NameplatePhotoRefused */
    public static function of(string $bytes, string $mediaType): self
    {
        if ('' === $bytes) {
            throw NameplatePhotoRefused::empty();
        }
        if (\strlen($bytes) > self::MAX_BYTES) {
            throw NameplatePhotoRefused::tooLarge();
        }
        if (!\in_array($mediaType, self::MEDIA_TYPES, true)) {
            throw NameplatePhotoRefused::format();
        }

        return new self($bytes, $mediaType);
    }
}
