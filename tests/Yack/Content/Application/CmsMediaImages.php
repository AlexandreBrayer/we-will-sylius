<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

/** Real images built with GD for the media library tests, and files that only pretend to be images. */
final class CmsMediaImages
{
    public const SVG = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>';

    public const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function jpeg(int $width = 64, int $height = 48): string
    {
        return self::encode('imagejpeg', $width, $height);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function png(int $width = 30, int $height = 20): string
    {
        return self::encode('imagepng', $width, $height);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function webp(int $width = 12, int $height = 9): string
    {
        return self::encode('imagewebp', $width, $height);
    }

    public static function gif(): string
    {
        return self::encode('imagegif', 4, 4);
    }

    /** PNG signature and header announcing $width x $height pixels, without image data. */
    public static function pngHeader(int $width, int $height): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
    }

    /**
     * @param callable(\GdImage): bool $encoder
     * @param positive-int               $width
     * @param positive-int               $height
     */
    private static function encode(callable $encoder, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        \assert(false !== $image);
        ob_start();
        $encoder($image);

        return (string) ob_get_clean();
    }
}
