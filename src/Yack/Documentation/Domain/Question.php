<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/**
 * A question asked to the documentation, from a product page (`productCode`) or not.
 * Bounded length: it is the only free text that may reach the language model.
 */
final readonly class Question
{
    public const MIN_LENGTH = 3;
    public const MAX_LENGTH = 300;
    public const PRODUCT_CODE_PATTERN = '/^[A-Z0-9][A-Z0-9-]{0,63}$/';

    private function __construct(
        public string $text,
        public ?string $productCode,
    ) {
    }

    public static function ask(string $text, ?string $productCode = null): self
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = mb_strlen($text);
        if ($length < self::MIN_LENGTH) {
            throw new InvalidQuestion('question', sprintf('Votre question doit comporter au moins %d caractères.', self::MIN_LENGTH));
        }
        if ($length > self::MAX_LENGTH) {
            throw new InvalidQuestion('question', sprintf('Votre question ne doit pas dépasser %d caractères.', self::MAX_LENGTH));
        }

        $code = null === $productCode ? '' : strtoupper(trim($productCode));
        if ('' !== $code && 1 !== preg_match(self::PRODUCT_CODE_PATTERN, $code)) {
            throw new InvalidQuestion('productCode', 'Code produit invalide.');
        }

        return new self($text, '' === $code ? null : $code);
    }
}
