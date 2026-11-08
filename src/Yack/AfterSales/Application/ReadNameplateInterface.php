<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Yack\AfterSales\Domain\NameplatePhotoRefused;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\Common\Domain\AiUnavailable;

/**
 * Point d'entrée public d'AfterSales (ADR 008) : ce qui est imprimé sur la photo d'une plaque signalétique ou d'une
 * étiquette, sans aucune recherche (ni parc installé, ni catalogue). Un autre contexte le lit au lieu de
 * l'Infrastructure d'AfterSales ; ses lecteurs sont listés dans le README du contexte.
 */
interface ReadNameplateInterface
{
    /**
     * Octets de la photo telle qu'envoyée (type lu sur le contenu, jamais sur le nom). La photo n'est pas conservée.
     *
     * @throws NameplatePhotoRefused photo vide, trop lourde, HEIC, ou ni JPEG, ni PNG, ni WebP
     * @throws AiUnavailable         le service de lecture ne peut pas répondre maintenant (adapter réel seulement)
     */
    public function read(string $bytes): NameplateReading;
}
