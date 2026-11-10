<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** Port: whether the Yack PIM/ERP source answers now (dashboard of the synchronisations). */
interface ProductSourceHealth
{
    public function check(): SourceHealth;
}
