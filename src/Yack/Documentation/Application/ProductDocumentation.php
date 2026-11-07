<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Application;

/** Notices of a product (titles) and the sample question offered on its page (null: none). */
final readonly class ProductDocumentation
{
    /** @param list<string> $notices */
    public function __construct(
        public string $productCode,
        public array $notices,
        public ?string $exampleQuestion,
    ) {
    }

    public function hasNotice(): bool
    {
        return [] !== $this->notices;
    }
}
