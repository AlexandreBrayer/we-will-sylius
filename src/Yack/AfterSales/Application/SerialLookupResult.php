<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Entity\AfterSales\Model;
use App\Yack\AfterSales\Domain\Warranty;

final readonly class SerialLookupResult
{
    /** @param list<Model> $models */
    public function __construct(
        public string $query,
        /** Normalised serial number, null when a ServiceCode was typed. */
        public ?string $serial,
        public array $models,
        public ?Warranty $warranty,
    ) {
    }

    public function isServiceCode(): bool
    {
        return [] !== $this->models && null === $this->serial;
    }
}
