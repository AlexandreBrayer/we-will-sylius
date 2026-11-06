<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

use App\Entity\AfterSales\ExplodedView;
use App\Entity\AfterSales\Model;

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
}
