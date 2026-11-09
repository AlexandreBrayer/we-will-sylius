<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

/**
 * Public entry point of AfterSales for the warranties of the installed base that end soon (ADR 008). Read by
 * Steering (alert « garanties qui expirent », table « Garanties à échéance »).
 */
interface WarrantyCalendarInterface
{
    /**
     * Coverages (parts, compressor) ending in [from, to], the soonest first.
     *
     * @return list<WarrantyEnding>
     */
    public function endingBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
