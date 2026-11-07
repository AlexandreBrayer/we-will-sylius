<?php

declare(strict_types=1);

namespace App\Yack\Common\Infrastructure\Image;

use App\Yack\Common\Domain\AiInput;

/**
 * Re-encodes an uploaded photo with GD before it leaves the server: EXIF (GPS position,
 * device, date) is dropped, the image is reduced to MAX_SIDE pixels on its longest side
 * (enough to read a nameplate, cheaper to send). JPEG and PNG stay in their format; WebP
 * (Android browsers) becomes JPEG, the formats every provider reads. HEIC is not read by GD.
 */
final class ExifStripper
{
    public const MAX_SIDE = 1600;

    private const JPEG_QUALITY = 88;

    /** Types read; the image sent is PNG for a PNG, JPEG otherwise. */
    public const READ_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function toInput(string $bytes): AiInput
    {
        $info = '' === $bytes ? false : @getimagesizefromstring($bytes);
        $type = false === $info ? null : ($info['mime'] ?? null);
        if (!\in_array($type, self::READ_TYPES, true)) {
            throw new \InvalidArgumentException('Photo refusée : formats JPEG, PNG ou WebP uniquement.');
        }

        $source = @imagecreatefromstring($bytes);
        if (false === $source) {
            throw new \InvalidArgumentException('Photo illisible.');
        }

        $image = $this->reduce($source);
        $png = 'image/png' === $type;
        ob_start();
        $png ? imagepng($image, null, 6) : imagejpeg($image, null, self::JPEG_QUALITY);
        $clean = (string) ob_get_clean();

        return AiInput::image($clean, $png ? 'image/png' : 'image/jpeg');
    }

    private function reduce(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);
        if ($longest <= self::MAX_SIDE) {
            return $image;
        }

        $ratio = self::MAX_SIDE / $longest;
        $reduced = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));

        return false === $reduced ? $image : $reduced;
    }
}
