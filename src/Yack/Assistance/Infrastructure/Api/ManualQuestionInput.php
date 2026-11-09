<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * POST /api/v2/shop/yack/assistance/questions {question, reference} : question aux manuels indexés du modèle (niveaux
 * exact et série seulement). Comptes connectés (PRO et ECOM) : 401 pour un visiteur. 200 {answer, sources:
 * [{documentId, title, page, url}], simulated} ; 422 sur `question` (3 à 300 caractères) ou `reference` (requise,
 * 40 caractères au plus) ; 503 si le modèle de langage est indisponible ; 404 si le module est coupé
 * (YACK_ASSISTANCE=0). Les bornes sont contrôlées par le processeur (`AskManuals::problems`), pas par le validateur.
 * Contrat : docs/API_FRONT.md, section 13.
 */
#[ApiResource(
    shortName: 'YackAssistanceQuestion',
    operations: [
        new Post(
            uriTemplate: '/shop/yack/assistance/questions',
            status: 200,
            output: ManualAnswerView::class,
            normalizationContext: ['groups' => ['assistance_answer:item']],
            denormalizationContext: ['groups' => ['assistance_question:write']],
            security: "is_granted('ROLE_USER')",
            validate: false,
            processor: ManualQuestionProcessor::class,
            name: ManualQuestionProcessor::OPERATION,
        ),
    ],
)]
final class ManualQuestionInput
{
    #[Groups(['assistance_question:write'])]
    public ?string $question = null;

    /** Référence validée par la personne (forme lue ou canonique). */
    #[Groups(['assistance_question:write'])]
    public ?string $reference = null;
}
