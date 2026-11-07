<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/**
 * A paragraph of a notice: what the retrieval finds and the only text a language model may read.
 * `productCodes`: catalogue codes (YK-…) the notice covers; `section`: heading above the paragraph.
 */
final readonly class Passage
{
    /** @var list<string> */
    public array $productCodes;

    /** @param list<string> $productCodes */
    public function __construct(
        public string $source,
        public int $page,
        public string $text,
        array $productCodes = [],
        public ?string $section = null,
    ) {
        if ('' === trim($text)) {
            throw new \InvalidArgumentException('Passage de documentation vide.');
        }
        new Citation($source, $page);

        $codes = [];
        foreach ($productCodes as $code) {
            $code = strtoupper(trim($code));
            if ('' !== $code && !\in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        $this->productCodes = $codes;
    }

    public function citation(): Citation
    {
        return new Citation($this->source, $this->page);
    }

    public function covers(string $productCode): bool
    {
        return \in_array(strtoupper(trim($productCode)), $this->productCodes, true);
    }

    /** Section heading and paragraph: what the retrieval indexes. */
    public function indexedText(): string
    {
        return null === $this->section ? $this->text : $this->section."\n".$this->text;
    }
}
