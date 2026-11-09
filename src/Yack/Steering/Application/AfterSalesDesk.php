<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Yack\AfterSales\Application\SerialLookupResult;
use App\Yack\AfterSales\Application\WarrantyEnding;
use App\Yack\Steering\Domain\PartDemand;

/**
 * « SAV et pièces » page: search by serial number, parts the most ordered over 30 days with their stock, warranties
 * ending within 90 days, references under the stock threshold.
 */
final readonly class AfterSalesDesk
{
    /**
     * @param list<PartDemand>     $parts
     * @param list<WarrantyEnding> $warranties
     */
    public function __construct(
        public SteeringSnapshot $snapshot,
        public string $query,
        public ?SerialLookupResult $lookup,
        public array $parts,
        public array $warranties,
    ) {
    }
}
