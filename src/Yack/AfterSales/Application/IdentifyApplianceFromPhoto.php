<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\Catalog\Application\ProductReferenceReaderInterface;
use App\Yack\Common\Domain\AiUnavailable;

/**
 * « La pièce par une photo »: photo of the rating plate or label → what is printed on it → first the same
 * lookup in the installed base as a manual search (AfterSalesCatalog), then, without a known serial number,
 * the model reference in the catalog (Catalog, through its Application interface, ADR 008): its product sheet
 * and documentation. The photo is only passed along, never kept.
 */
final readonly class IdentifyApplianceFromPhoto
{
    public function __construct(
        private NameplateReader $reader,
        private AfterSalesCatalog $catalog,
        private ProductReferenceReaderInterface $products,
    ) {
    }

    public function __invoke(NameplatePhoto $photo): ApplianceIdentification
    {
        try {
            $reading = $this->reader->read($photo);
        } catch (AiUnavailable) {
            return ApplianceIdentification::unavailable();
        }

        if (null !== $reading->serialNumber && [] !== $this->catalog->findModels($reading->serialNumber)) {
            return ApplianceIdentification::found($reading);
        }

        foreach ($reading->productHints() as $hint) {
            $product = $this->products->find($hint);
            if (null !== $product) {
                return ApplianceIdentification::product($reading, $product);
            }
        }

        return $reading->isReadable() ? ApplianceIdentification::unknown($reading) : ApplianceIdentification::unreadable($reading);
    }
}
