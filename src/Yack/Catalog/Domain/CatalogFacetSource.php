<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** Port: filterable values of the products of a shop listing (read only). */
interface CatalogFacetSource
{
    /**
     * Enabled products of the shop, of the given kind (all kinds when null).
     *
     * @return list<ProductFacetValues>
     */
    public function listedProducts(?ProductKind $kind): array;
}
