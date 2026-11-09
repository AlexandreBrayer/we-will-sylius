<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

use App\Entity\AfterSales\ExplodedView;
use App\Entity\AfterSales\Model;
use App\Entity\AfterSales\SerialNumber;

/**
 * Port: installed base & technical documentation (serial numbers, models, exploded views).
 */
interface AfterSalesCatalog
{
    /**
     * Models matching a serial number, or a ServiceCode typed directly by the technician.
     *
     * @return list<Model>
     */
    public function findModels(string $serialOrServiceCode): array;

    public function findModelByServiceCode(string $serviceCode): ?Model;

    public function findExplodedView(Model $model, int $explodedViewId): ?ExplodedView;

    public function findExplodedViewById(int $explodedViewId): ?ExplodedView;

    /**
     * The whole installed base, models loaded with it (one query), by serial number.
     *
     * @return list<SerialNumber>
     */
    public function installedBase(): array;
}
