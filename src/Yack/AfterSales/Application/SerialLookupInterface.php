<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

/**
 * Public entry point of AfterSales for the search by serial number (ADR 008): what was typed (serial number or
 * ServiceCode) to models and warranty. Read by Steering (« SAV et pièces » of the back-office).
 */
interface SerialLookupInterface
{
    public function __invoke(string $query): SerialLookupResult;
}
