<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * Photo of a rating plate or of the label of an appliance, as uploaded: kept in memory for the reading
 * only, never stored. The media type is the one read on the content (finfo), never the file name or the
 * Content-Type sent. HEIC (iPhone) is converted by the browser before the upload; when it arrives as is,
 * it gets its own message.
 */
final readonly class NameplatePhoto
{
    /** Same limit as the front check; nginx, adapter-node and PHP accept a little more (docs/adr/0005-fichiers-televerses.md). */
    public const MAX_BYTES = 20 * 1024 * 1024;

    public const MEDIA_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const HEIC_TYPES = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];

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
        if (\in_array($mediaType, self::HEIC_TYPES, true)) {
            throw NameplatePhotoRefused::heic();
        }
        if (!\in_array($mediaType, self::MEDIA_TYPES, true)) {
            throw NameplatePhotoRefused::format();
        }

        return new self($bytes, $mediaType);
    }
}
