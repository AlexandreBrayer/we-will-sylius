<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;

/**
 * Demo adapter (YACK_AI_ADAPTER=fake, the default): reads nothing on the image itself. It recognises
 * the FICTIF demo plates of public/yack/demo/plaques/ by the marker that bin/yack-demo-plaques.php
 * writes in them (PNG text chunk MARKER, JSON {"serial", "reference", "model", "brand"}, `brand` null or
 * absent when no brand is printed); any other photo (JPEG, WebP, PNG without the marker) is unreadable.
 * Deterministic, no network, the photo is not kept.
 */
final class FakeNameplateReader implements NameplateReader
{
    /** Keyword of the PNG tEXt chunk carrying what is printed on a demo plate. */
    public const MARKER = 'YackDemoNameplate';

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /** Chunks scanned at most: the marker is written before the image data. */
    private const MAX_CHUNKS = 64;

    public function read(NameplatePhoto $photo): NameplateReading
    {
        return $this->readDemoPlate($photo) ?? NameplateReading::unreadable(simulated: true);
    }

    /**
     * Reading of a FICTIF demo plate (the MARKER chunk is present and intact), null for any other photo.
     * Also the fallback of NameplateReaderSelector when the real model is unavailable.
     */
    public function readDemoPlate(NameplatePhoto $photo): ?NameplateReading
    {
        $printed = 'image/png' === $photo->mediaType ? self::marker($photo->bytes) : null;
        if (null === $printed) {
            return null;
        }

        return NameplateReading::read($printed['serial'], $printed['model'], simulated: true, reference: $printed['reference'], brand: $printed['brand']);
    }

    /**
     * Content of the MARKER chunk of a PNG, null when absent or damaged.
     *
     * @return array{serial: ?string, reference: ?string, model: ?string, brand: ?string}|null
     */
    private static function marker(string $png): ?array
    {
        if (!str_starts_with($png, self::PNG_SIGNATURE)) {
            return null;
        }

        $offset = \strlen(self::PNG_SIGNATURE);
        for ($i = 0; $i < self::MAX_CHUNKS && $offset + 12 <= \strlen($png); ++$i) {
            $length = unpack('N', substr($png, $offset, 4));
            $length = \is_array($length) ? (int) $length[1] : -1;
            if ($length < 0 || $offset + 12 + $length > \strlen($png)) {
                return null;
            }

            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);
            $crc = unpack('N', substr($png, $offset + 8 + $length, 4));
            if (!\is_array($crc) || crc32($type.$data) !== $crc[1]) {
                return null;
            }
            if ('IDAT' === $type || 'IEND' === $type) {
                return null;
            }
            if ('tEXt' === $type && str_starts_with($data, self::MARKER."\0")) {
                return self::decode(substr($data, \strlen(self::MARKER) + 1));
            }

            $offset += 12 + $length;
        }

        return null;
    }

    /** @return array{serial: ?string, reference: ?string, model: ?string, brand: ?string}|null */
    private static function decode(string $json): ?array
    {
        $printed = json_decode($json, true);
        if (!\is_array($printed)) {
            return null;
        }

        return [
            'serial' => \is_string($printed['serial'] ?? null) ? $printed['serial'] : null,
            'reference' => \is_string($printed['reference'] ?? null) ? $printed['reference'] : null,
            'model' => \is_string($printed['model'] ?? null) ? $printed['model'] : null,
            'brand' => \is_string($printed['brand'] ?? null) ? $printed['brand'] : null,
        ];
    }
}
