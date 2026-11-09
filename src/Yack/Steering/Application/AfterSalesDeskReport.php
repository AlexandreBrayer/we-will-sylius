<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Yack\AfterSales\Application\SerialLookupInterface;
use App\Yack\AfterSales\Application\WarrantyCalendarInterface;
use App\Yack\Catalog\Application\CatalogBranchesInterface;
use App\Yack\Steering\Domain\SalesFacts;

/**
 * Builds the « SAV et pièces » page. The search goes through AfterSales (same lookup as the shop SAV page).
 */
final readonly class AfterSalesDeskReport
{
    public const PARTS_DAYS = 30;

    public const PARTS_LISTED = 8;

    public const WARRANTY_DAYS = 90;

    /** Longest query searched: a serial number or a ServiceCode, with spaces or dashes. */
    public const QUERY_MAX_LENGTH = 64;

    public function __construct(
        private SalesFacts $facts,
        private AlertBoard $alerts,
        private SerialLookupInterface $serialLookup,
        private WarrantyCalendarInterface $warranties,
        private CatalogBranchesInterface $branches,
    ) {
    }

    public function build(string $query): AfterSalesDesk
    {
        $snapshot = $this->alerts->snapshot();
        $now = $snapshot->now;
        $query = mb_substr(trim($query), 0, self::QUERY_MAX_LENGTH);

        return new AfterSalesDesk(
            $snapshot,
            $query,
            '' === $query ? null : ($this->serialLookup)($query),
            $this->facts->mostOrderedParts($this->branches->sparePartsRootCode(), $now->modify(sprintf('-%d days', self::PARTS_DAYS)), $now, self::PARTS_LISTED),
            $this->warranties->endingBetween($now->setTime(0, 0), $now->setTime(23, 59, 59)->modify(sprintf('+%d days', self::WARRANTY_DAYS))),
        );
    }
}
