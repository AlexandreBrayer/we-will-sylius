<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\SerialNumberFormat;
use App\Yack\Common\Domain\AiUnavailable;

/**
 * "La pièce par une photo" (IA-05): photo of the rating plate → serial number read → same lookup
 * in the installed base as a manual search (AfterSalesCatalog). The photo is only passed along,
 * never kept.
 */
final readonly class IdentifyApplianceFromPhoto
{
    public function __construct(
        private NameplateReader $reader,
        private AfterSalesCatalog $catalog,
    ) {
    }

    public function __invoke(NameplatePhoto $photo): ApplianceIdentification
    {
        try {
            $reading = $this->reader->read($photo);
        } catch (AiUnavailable) {
            return ApplianceIdentification::unavailable();
        }

        if (!$reading->isReadable()) {
            return ApplianceIdentification::unreadable($reading);
        }

        $models = $this->catalog->findModels(SerialNumberFormat::normalize((string) $reading->serialNumber));

        return [] === $models ? ApplianceIdentification::unknown($reading) : ApplianceIdentification::found($reading);
    }
}
