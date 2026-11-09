<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

use App\Yack\Common\Domain\AiUnavailable;

/**
 * Port : répond à une question à partir du texte indexé de ces manuels seulement (jamais d'autre chose).
 * Chaque source de la réponse est une page indexée de l'un d'eux ; rien de pertinent : `ManualAnswer::notFound()`.
 */
interface ManualAnswerer
{
    /**
     * @param non-empty-list<Manual> $manuals documents de niveau exact ou série, avec texte indexé
     *
     * @throws AiUnavailable quand le modèle de langage ne peut pas répondre maintenant
     */
    public function answer(string $question, array $manuals): ManualAnswer;
}
