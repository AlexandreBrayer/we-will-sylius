<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/**
 * Produit fini du catalogue cité par au moins un document. `family` : codes des autres puissances du
 * même modèle (champ du catalogue), le produit compris.
 */
final readonly class ManualProduct
{
    /** @param list<string> $family */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $brand = null,
        public array $family = [],
    ) {
        if ('' === trim($code)) {
            throw InvalidManual::library('produit sans code');
        }
    }

    /** @return list<string> le code et sa famille, sans doublon */
    public function codes(): array
    {
        return array_values(array_unique([$this->code, ...$this->family]));
    }
}
