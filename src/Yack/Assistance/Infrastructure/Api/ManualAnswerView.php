<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Api;

use App\Yack\Assistance\Domain\ManualAnswer;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Réponse de POST /shop/yack/assistance/questions ; `sources` vide seulement pour « je ne trouve pas ». `url` : lien
 * du PDF officiel, sans numéro de page (le front l'ajoute en `#page=N`).
 */
final readonly class ManualAnswerView
{
    /** @param list<array{documentId: string, title: string, page: int, url: string}> $sources */
    public function __construct(
        #[Groups(['assistance_answer:item'])]
        public string $answer,
        #[Groups(['assistance_answer:item'])]
        public array $sources,
        /** Vrai quand l'adapter de démonstration a répondu (YACK_AI_ADAPTER=fake). */
        #[Groups(['assistance_answer:item'])]
        public bool $simulated,
    ) {
    }

    public static function of(ManualAnswer $answer): self
    {
        return new self(
            $answer->text,
            array_map(static fn (array $citation): array => [
                'documentId' => $citation['manual']->id,
                'title' => $citation['manual']->title,
                'page' => $citation['page'],
                'url' => $citation['manual']->url,
            ], $answer->citations),
            $answer->simulated,
        );
    }
}
