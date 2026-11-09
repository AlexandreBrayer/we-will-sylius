<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/** Port : la base des manuels (documents officiels et produits du catalogue qu'ils couvrent). */
interface ManualLibrary
{
    /**
     * @return list<Manual>
     *
     * @throws InvalidManual
     */
    public function documents(): array;

    /**
     * @return list<ManualProduct>
     *
     * @throws InvalidManual
     */
    public function products(): array;
}
