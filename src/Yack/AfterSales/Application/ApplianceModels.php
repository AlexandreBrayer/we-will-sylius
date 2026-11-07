<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Yack\AfterSales\Domain\AfterSalesCatalog;

/** Lit le parc installé (`AfterSalesCatalog::findModels`, lecture seule) et rend les codes des produits finis. */
final readonly class ApplianceModels implements ApplianceModelsInterface
{
    public function __construct(private AfterSalesCatalog $catalog)
    {
    }

    public function productCodes(string $serialOrServiceCode): ?array
    {
        if ('' === trim($serialOrServiceCode)) {
            return null;
        }

        $models = $this->catalog->findModels($serialOrServiceCode);
        if ([] === $models) {
            return null;
        }

        $codes = [];
        foreach ($models as $model) {
            $code = $model->getFinishedProduct()?->getCode();
            if (\is_string($code) && '' !== $code && !\in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
