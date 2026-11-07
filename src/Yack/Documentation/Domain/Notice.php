<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/**
 * A notice of the corpus: title (the source cited), name of the product it describes (words that
 * identify the device, not the subject of a question), products covered, sample question, passages.
 */
final readonly class Notice
{
    /**
     * @param list<string>  $productCodes
     * @param list<Passage> $passages
     */
    public function __construct(
        public string $title,
        public string $productName,
        public array $productCodes,
        public ?string $exampleQuestion,
        public array $passages,
    ) {
        if ('' === trim($title)) {
            throw new \InvalidArgumentException('Notice sans titre.');
        }
    }

    public function covers(string $productCode): bool
    {
        return \in_array(strtoupper(trim($productCode)), array_map(strtoupper(...), $this->productCodes), true);
    }
}
