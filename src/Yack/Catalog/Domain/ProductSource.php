<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Port: where the Yack master catalog comes from (PIM/ERP API, file drop, ...).
 */
interface ProductSource
{
    /**
     * @param string $feed opaque batch identifier (file path for the JSON adapter, endpoint/cursor for an API adapter)
     *
     * @throws ProductSourceUnavailable when the whole batch cannot be read
     */
    public function fetch(string $feed): ProductFeed;
}
