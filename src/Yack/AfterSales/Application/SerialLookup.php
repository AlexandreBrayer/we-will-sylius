<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Domain\SerialNumberFormat;
use App\Yack\AfterSales\Domain\WarrantyProvider;

/**
 * SAV entry point: what the technician typed (serial number or ServiceCode) → models + warranty.
 */
final readonly class SerialLookup
{
    public function __construct(
        private AfterSalesCatalog $catalog,
        private WarrantyProvider $warrantyProvider,
    ) {
    }

    public function __invoke(string $query): SerialLookupResult
    {
        $query = trim($query);
        if ('' === $query) {
            return new SerialLookupResult($query, null, [], null);
        }

        $models = $this->catalog->findModels($query);
        $isServiceCode = 1 === count($models) && $models[0]->getServiceCode() === strtoupper($query);

        return new SerialLookupResult(
            $query,
            $isServiceCode ? null : SerialNumberFormat::normalize($query),
            $models,
            [] === $models || $isServiceCode ? null : $this->warrantyProvider->forSerialNumber($query),
        );
    }
}
