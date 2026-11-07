<?php

declare(strict_types=1);

namespace App\Yack\Common\Infrastructure\Image;

use App\Yack\Common\Domain\AiInput;

/**
 * Re-encodes an uploaded photo with GD before it leaves the server: EXIF (GPS position,
 * device, date) is dropped, the image is reduced to MAX_SIDE pixels on its longest side
 * (enough to read a nameplate, cheaper to send). JPEG and PNG only.
 */
final class ExifStripper
{
    public const MAX_SIDE = 1600;

    private const JPEG_QUALITY = 88;

    public function toInput(string $bytes): AiInput
    {
        $info = '' === $bytes ? false : @getimagesizefromstring($bytes);
        $type = false === $info ? null : ($info['mime'] ?? null);
        if (!\in_array($type, ['image/jpeg', 'image/png'], true)) {
            throw new \InvalidArgumentException('Photo refusée : formats JPEG ou PNG uniquement.');
        }

        $source = @imagecreatefromstring($bytes);
        if (false === $source) {
            throw new \InvalidArgumentException('Photo illisible.');
        }

        $image = $this->reduce($source);
        ob_start();
        'image/png' === $type ? imagepng($image, null, 6) : imagejpeg($image, null, self::JPEG_QUALITY);
        $clean = (string) ob_get_clean();

        return AiInput::image($clean, $type);
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
