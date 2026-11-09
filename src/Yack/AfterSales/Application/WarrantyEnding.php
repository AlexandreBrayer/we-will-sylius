<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

/**
 * A coverage of an installed appliance that ends on a given day: parts (« Pièces ») or compressor (« Compresseur »).
 */
final readonly class WarrantyEnding
{
    public const PARTS = 'Pièces';
    public const COMPRESSOR = 'Compresseur';

    /** @param list<string> $models names of the models of the appliance */
    public function __construct(
        public string $serial,
        public array $models,
        public string $coverage,
        public \DateTimeImmutable $endsAt,
        public string $source,
    ) {
    }
}
