<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Yack\Assistance\Application\PlateReading;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * POST /api/v2/shop/yack/assistance/plate-readings (public, multipart/form-data, champ `photo` : JPEG, PNG ou WebP,
 * 20 Mo au plus, type lu sur le contenu) : ce qui est lu sur la plaque, pour l'écran de validation, sans aucune
 * recherche de documents. 200 {serialNumber, referenceRead, reference, model, brand, brandSource, simulated} ;
 * 422 sur `photo` ; 503 si la lecture est indisponible ; 404 si le module est coupé (YACK_ASSISTANCE=0).
 * La photo n'est jamais conservée. Contrat : docs/API_FRONT.md, section 13.
 */
#[ApiResource(
    shortName: 'YackAssistancePlateReading',
    operations: [
        new Post(
            uriTemplate: '/shop/yack/assistance/plate-readings',
            status: 200,
            inputFormats: ['multipart' => ['multipart/form-data']],
            normalizationContext: ['groups' => ['assistance_plate_reading:item']],
            input: false,
            // read: with Symfony listeners (Sylius), only the read step sets the request data; there is no provider,
            // so the data is null and the processor gets the photo from the request. With read: false the placeholder
            // controller of API Platform finds no data and fails (500).
            read: true,
            deserialize: false,
            validate: false,
            processor: PlateReadingProcessor::class,
            name: PlateReadingProcessor::OPERATION,
        ),
    ],
)]
final readonly class PlateReadingView
{
    public function __construct(
        /** N° de série lu, normalisé (majuscules, sans espaces ni tirets), null si aucun n'est lu. */
        #[Groups(['assistance_plate_reading:item'])]
        public ?string $serialNumber,
        /** Référence telle que lue sur la plaque. */
        #[Groups(['assistance_plate_reading:item'])]
        public ?string $referenceRead,
        /** Forme canonique de la référence (corrigée si besoin), null si rien d'exploitable n'est lu. */
        #[Groups(['assistance_plate_reading:item'])]
        public ?string $reference,
        /** Désignation du modèle telle que lue. */
        #[Groups(['assistance_plate_reading:item'])]
        public ?string $model,
        /** Marque lue sur la plaque (au nom du catalogue quand elle le désigne), sinon déduite de la référence. */
        #[Groups(['assistance_plate_reading:item'])]
        public ?string $brand,
        /** plaque | deduite | null */
        #[Groups(['assistance_plate_reading:item'])]
        public ?string $brandSource,
        /** Vrai quand l'adapter de démonstration a répondu (YACK_AI_ADAPTER=fake). */
        #[Groups(['assistance_plate_reading:item'])]
        public bool $simulated,
    ) {
    }

    public static function of(PlateReading $reading): self
    {
        return new self(
            $reading->serialNumber,
            $reading->referenceRead,
            $reading->reference,
            $reading->model,
            $reading->brand,
            $reading->brandSource,
            $reading->simulated,
        );
    }
}
