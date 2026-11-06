<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/**
 * Port: warranty dates come from the Yack information system (ERP / warranty registration API).
 */
interface WarrantyProvider
{
    /** Returns null when the equipment is unknown to the warranty system. */
    public function forSerialNumber(string $serialNumber): ?Warranty;
}
