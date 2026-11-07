<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

/**
 * Point d'entrée public d'AfterSales (ADR 008) : les produits finis d'un appareil du parc installé. Assistance
 * le lit pour chercher les manuels des modèles d'un n° de série, jamais par l'Infrastructure d'AfterSales.
 */
interface ApplianceModelsInterface
{
    /**
     * Codes catalogue des produits finis des modèles de l'appareil (n° de série ou ServiceCode, casse, espaces et
     * tirets tolérés), dans l'ordre des modèles, sans doublon. Null si l'appareil est absent du parc ; liste vide
     * s'il est connu mais qu'aucun de ses modèles n'est relié à un produit fini.
     *
     * @return list<string>|null
     */
    public function productCodes(string $serialOrServiceCode): ?array;
}
