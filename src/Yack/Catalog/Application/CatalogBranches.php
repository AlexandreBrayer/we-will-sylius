<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductKind;

final readonly class CatalogBranches implements CatalogBranchesInterface
{
    public function finishedProductsRootCode(): string
    {
        return ProductKind::Finished->catalogTaxonCode();
    }

    public function sparePartsRootCode(): string
    {
        return ProductKind::SparePart->catalogTaxonCode();
    }
}
