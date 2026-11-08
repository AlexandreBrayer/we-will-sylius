<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Application;

use App\Yack\AfterSales\Application\ReadNameplateInterface;
use App\Yack\AfterSales\Domain\NameplatePhotoRefused;
use App\Yack\Assistance\Domain\ModelReference;
use App\Yack\Assistance\Domain\ReferenceBrand;
use App\Yack\Common\Domain\AiUnavailable;

/**
 * Photo de la plaque → ce qui y est lu, sans chercher aucun document : la lecture est celle d'AfterSales (interface
 * d'Application, ADR 008), la référence est ramenée à sa forme canonique (`ModelReference`), la marque est celle de
 * la plaque, sinon celle que donne le préfixe de la référence (`ReferenceBrand`, dite « déduite »). La recherche
 * n'est lancée qu'après l'écran de validation, avec ce que la personne a confirmé ou corrigé.
 */
final readonly class ReadPlate
{
    public const UNAVAILABLE_MESSAGE = 'Lecture indisponible : saisissez la référence';

    public function __construct(private ReadNameplateInterface $nameplate)
    {
    }

    /**
     * @throws NameplatePhotoRefused photo refusée (vide, trop lourde, HEIC, format)
     * @throws AiUnavailable         lecture indisponible
     */
    public function __invoke(string $photo): PlateReading
    {
        $reading = $this->nameplate->read($photo);
        $reference = null === $reading->reference ? null : ModelReference::fromInput($reading->reference);

        [$brand, $brandSource] = [null, null];
        if (null !== $reading->brand) {
            [$brand, $brandSource] = [$reading->brand, PlateReading::BRAND_FROM_PLATE];
        } elseif (null !== $reference && null !== ($deduced = ReferenceBrand::of($reference))) {
            [$brand, $brandSource] = [$deduced->name, ReferenceBrand::SOURCE];
        }

        return new PlateReading(
            $reading->serialNumber,
            $reading->reference,
            $reference?->canonical,
            $reading->model,
            $brand,
            $brandSource,
            $reading->simulated,
        );
    }
}
