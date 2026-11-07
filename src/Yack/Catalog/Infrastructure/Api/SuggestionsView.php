<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Api;

final readonly class SuggestionsView
{
    /**
     * @param list<array{code: string, name: string, slug: string, brand: string|null, image: string|null}> $products
     * @param list<array{code: string, name: string, slug: string}>                                        $categories
     */
    public function __construct(
        public string $query,
        public string $engine,
        public array $products,
        public array $categories,
    ) {
    }
}
