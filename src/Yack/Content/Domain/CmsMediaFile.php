<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Image sent to the media library of the builder, as checked before it is stored
 * (docs/adr/0005-fichiers-televerses.md): JPEG, PNG or WebP read on the content (finfo), never on the
 * file name or the Content-Type sent; no SVG (it can carry script); 20 Mo at most; a size in pixels
 * that the thumbnails can decode. The stored name is random, the name sent is kept as data only.
 */
final readonly class CmsMediaFile
{
    /** Same limit as the front check and the other uploads; nginx, adapter-node and PHP accept a little more. */
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** Decoding a larger image for its thumbnail would take hundreds of megabytes. */
    public const MAX_PIXELS = 40_000_000;

    /** Media type read on the content => extension of the stored file. */
    public const MEDIA_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Directory of the uploaded files, relative to the Sylius public dir (served by nginx under /media/). */
    public const DIRECTORY = 'media/yack-cms';

    /** Path of an uploaded file: random name, extension of its verified type. */
    public const PATH_PATTERN = '#^media/yack-cms/[a-f0-9]{32}\.(jpg|png|webp)$#D';

    public const NAME_MAX = 255;

    public const ALT_MAX = 255;

    private function __construct(
        public string $bytes,
        public string $mediaType,
        public int $width,
        public int $height,
    ) {
    }

    /**
     * @throws CmsMediaRefused
     */
    public static function read(string $bytes): self
    {
        if ('' === $bytes) {
            throw CmsMediaRefused::empty();
        }
        if (\strlen($bytes) > self::MAX_BYTES) {
            throw CmsMediaRefused::tooLarge();
        }

        $mediaType = (string) (new \finfo(\FILEINFO_MIME_TYPE))->buffer($bytes);
        if (str_starts_with($mediaType, 'image/svg')) {
            throw CmsMediaRefused::svg();
        }
        if (!\array_key_exists($mediaType, self::MEDIA_TYPES)) {
            throw CmsMediaRefused::format();
        }

        $size = @getimagesizefromstring($bytes);
        if (false === $size || $size[0] < 1 || $size[1] < 1) {
            throw CmsMediaRefused::unreadable();
        }
        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw CmsMediaRefused::tooManyPixels();
        }

        return new self($bytes, $mediaType, $size[0], $size[1]);
    }

    public function extension(): string
    {
        return self::MEDIA_TYPES[$this->mediaType];
    }

    public function size(): int
    {
        return \strlen($this->bytes);
    }

    public static function isUploadedPath(string $path): bool
    {
        return 1 === preg_match(self::PATH_PATTERN, $path);
    }

    /**
     * Name sent by the browser, kept as data only (never used for a path): last segment of a path,
     * without control characters, spaces collapsed, NAME_MAX characters at most; « image » when nothing is left.
     */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = self::cleanLine($name);
        $name = mb_substr($name, 0, self::NAME_MAX);

        return '' === $name || '.' === $name || '..' === $name ? 'image' : $name;
    }

    /**
     * Alternative text: one line, without control characters, trimmed.
     *
     * @throws CmsMediaRefused longer than ALT_MAX characters
     */
    public static function cleanAlt(string $alt): string
    {
        $alt = self::cleanLine($alt);
        if (mb_strlen($alt) > self::ALT_MAX) {
            throw CmsMediaRefused::altTooLong();
        }

        return $alt;
    }

    private static function cleanLine(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        $text = (string) preg_replace('/[\p{C}\s]+/u', ' ', $text);

        return trim($text);
    }
}
