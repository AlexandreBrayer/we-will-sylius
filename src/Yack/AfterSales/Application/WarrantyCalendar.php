<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Entity\AfterSales\Model;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Domain\WarrantyProvider;

/**
 * Warranties of the installed base ending in a period: every serial number of the installed base, its warranty from
 * the WarrantyProvider (simulated dates in the POC), each coverage whose end falls in the period.
 */
final readonly class WarrantyCalendar implements WarrantyCalendarInterface
{
    public function __construct(
        private AfterSalesCatalog $catalog,
        private WarrantyProvider $warrantyProvider,
    ) {
    }

    public function endingBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $endings = [];
        foreach ($this->catalog->installedBase() as $serialNumber) {
            $warranty = $this->warrantyProvider->forSerialNumber($serialNumber->getSerial());
            if (null === $warranty) {
                continue;
            }
            $models = array_values(array_map(static fn (Model $model): string => $model->getName(), $serialNumber->getModels()->toArray()));
            foreach ([WarrantyEnding::PARTS => $warranty->partsCoverageEnd, WarrantyEnding::COMPRESSOR => $warranty->compressorCoverageEnd] as $coverage => $end) {
                if ($end >= $from && $end <= $to) {
                    $endings[] = new WarrantyEnding($warranty->serialNumber, $models, $coverage, $end, $warranty->source);
                }
            }
        }

        usort($endings, static fn (WarrantyEnding $a, WarrantyEnding $b): int => [$a->endsAt, $a->serial] <=> [$b->endsAt, $b->serial]);

        return $endings;
    }
}
