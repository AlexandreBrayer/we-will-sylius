<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Port: reads one record in the format of the Yack PIM/ERP API feed (`sku`, `label`, `price.amount`,
 * `specs`...) into a product. The API feed, the manual imports and the replay of rejected records share it.
 */
interface ProductRecordMapper
{
    /**
     * @param array<mixed> $record
     * @param string|null  $imageDir directory the image paths of the record are relative to, null when the record has no image
     *
     * @throws InvalidSourceProduct
     */
    public function map(array $record, ?string $imageDir = null): SourceProduct;
}
