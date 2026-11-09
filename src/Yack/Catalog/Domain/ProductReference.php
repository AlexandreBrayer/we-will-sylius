<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * A catalog product designated by its reference (Yack code, printed on the label of the appliance):
 * what another context needs to point to its product sheet and recall its documentation.
 * `documents`: names of the documents Yack publishes for the product (Sylius attribute DOCUMENTATION).
 */
final readonly class ProductReference
{
    /** Sylius text attribute holding the document names, joined by DOCUMENT_SEPARATOR. */
    public const DOCUMENTATION_ATTRIBUTE = 'documentation';

    public const DOCUMENT_SEPARATOR = ', ';

    /** Longest reference looked up: longer text is a sentence read by mistake, not a reference. */
    private const MAX_LENGTH = 40;

    /** Upper case letters, digits and `-/.`, at least one letter, 3 characters or more (SRK25ZS-W, FDCW-VNX-A, YORABTM200). */
    private const PLAUSIBLE = '/^(?=[^A-Z]*[A-Z])[A-Z0-9][A-Z0-9\-\/.]{2,39}$/';

    /** @param list<string> $documents */
    public function __construct(
        public string $code,
        public string $name,
        public string $slug,
        public ?string $brand = null,
        public array $documents = [],
    ) {
    }

    /**
     * Codes to look up for a reference as read on a label, most likely first: upper case, dashes
     * unified, spaces dropped (`srk25zs – w` → SRK25ZS-W), then spaces read as dashes (`SRK25ZS W`
     * → SRK25ZS-W). Empty when the text does not look like a reference.
     *
     * @return list<string>
     */
    public static function candidates(string $printed): array
    {
        $text = mb_strtoupper(trim($printed));
        $text = (string) preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $text);
        $text = (string) preg_replace('/\s*-\s*/u', '-', $text);
        if ('' === $text || mb_strlen($text) > self::MAX_LENGTH) {
            return [];
        }

        $codes = [];
        foreach ([(string) preg_replace('/\s+/u', '', $text), (string) preg_replace('/\s+/u', '-', $text)] as $code) {
            if (1 === preg_match(self::PLAUSIBLE, $code) && !\in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Document names from the value of the DOCUMENTATION_ATTRIBUTE attribute, without blanks or repeats.
     *
     * @return list<string>
     */
    public static function documentsFrom(mixed $value): array
    {
        if (!\is_string($value)) {
            return [];
        }

        $documents = [];
        foreach (explode(self::DOCUMENT_SEPARATOR, $value) as $document) {
            $document = trim($document);
            if ('' !== $document && !\in_array(mb_strtolower($document), array_map('mb_strtolower', $documents), true)) {
                $documents[] = $document;
            }
        }

        return $documents;
    }
}
