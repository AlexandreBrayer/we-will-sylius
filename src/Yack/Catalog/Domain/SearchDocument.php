<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * What the search engine knows of a product: the texts a customer may type (code, manufacturer
 * reference, name, brand, range, typology, categories, short description) and what a suggestion
 * shows. Never a price: prices depend on the customer profile and stay in the catalogue API.
 */
final readonly class SearchDocument
{
    /**
     * @param list<string> $categoryCodes codes of the main category and of its ancestors, nearest first
     * @param list<string> $categoryPath  names of the main category and of its ancestors, from the menu root down
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $slug,
        public bool $enabled,
        public ?ProductKind $kind = null,
        public ?string $brand = null,
        public ?string $range = null,
        public ?string $typology = null,
        public ?string $manufacturerReference = null,
        public array $categoryCodes = [],
        public array $categoryPath = [],
        public ?string $shortDescription = null,
        public ?string $imagePath = null,
    ) {
    }
}
