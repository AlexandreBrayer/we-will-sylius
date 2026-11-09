<?php

declare(strict_types=1);

namespace App\Yack\Common\Domain;

/**
 * One part of what is sent to the language model: text, an image (JPEG or PNG, EXIF already
 * removed) or a PDF. Built by the adapters of each context, never from a raw upload.
 */
final readonly class AiInput
{
    public const TEXT = 'text';
    public const IMAGE = 'image';
    public const PDF = 'pdf';

    private const IMAGE_TYPES = ['image/jpeg', 'image/png'];

    private function __construct(
        public string $kind,
        public string $content,
        public ?string $mediaType,
    ) {
    }

    public static function text(string $text): self
    {
        if ('' === trim($text)) {
            throw new \InvalidArgumentException('Texte vide.');
        }

        return new self(self::TEXT, $text, null);
    }

    public static function image(string $bytes, string $mediaType): self
    {
        if (!\in_array($mediaType, self::IMAGE_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Type d\'image refusé : %s.', $mediaType));
        }
        if ('' === $bytes) {
            throw new \InvalidArgumentException('Image vide.');
        }

        return new self(self::IMAGE, $bytes, $mediaType);
    }

    public static function pdf(string $bytes): self
    {
        if (!str_starts_with($bytes, '%PDF-')) {
            throw new \InvalidArgumentException('Ce fichier n\'est pas un PDF.');
        }

        return new self(self::PDF, $bytes, 'application/pdf');
    }
}
