<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/**
 * Réponse à une question posée aux manuels d'un modèle. Règle : une réponse trouvée cite toujours au moins une
 * page d'un manuel ; les seules réponses sans source sont « je ne trouve pas » (manuels lus, rien de pertinent) et
 * « aucun manuel indexé » (rien à lire pour ce modèle).
 *
 * `fromTable` : une source au moins est une page de tableau, dont la mise en page est perdue dans le texte indexé
 * (lignes et colonnes mélangées) ; l'avertissement est ajouté au texte. `simulated` : réponse de l'adapter de
 * démonstration.
 */
final readonly class ManualAnswer
{
    public const NOT_FOUND = 'Je ne trouve pas la réponse dans les manuels indexés de ce modèle.';

    public const NOT_INDEXED = 'Je ne trouve pas la réponse : aucun manuel de ce modèle n\'est indexé pour les questions. Les documents de la liste s\'ouvrent en PDF.';

    public const TABLE_WARNING = 'Attention : passage tiré d\'un tableau du manuel, dont les lignes et les colonnes sont mélangées dans le texte indexé. Vérifiez la valeur sur le PDF, à la page citée.';

    /** @param list<array{manual: Manual, page: int}> $citations */
    private function __construct(
        public string $text,
        public array $citations,
        public bool $simulated,
        public bool $fromTable,
    ) {
    }

    /**
     * @param list<array{manual: Manual, page: int}> $citations
     *
     * @throws \InvalidArgumentException texte vide, aucune source, page inférieure à 1
     */
    public static function cited(string $text, array $citations, bool $simulated = false, bool $fromTable = false): self
    {
        $text = trim($text);
        if ('' === $text) {
            throw new \InvalidArgumentException('Réponse vide.');
        }

        $unique = [];
        foreach ($citations as $citation) {
            if ($citation['page'] < 1) {
                throw new \InvalidArgumentException(sprintf('Page de manuel invalide : %d.', $citation['page']));
            }
            $unique[$citation['manual']->id . "\u{0}" . $citation['page']] ??= $citation;
        }
        if ([] === $unique) {
            throw new \InvalidArgumentException(sprintf('Réponse sans source refusée : « %s ».', mb_substr($text, 0, 80)));
        }

        return new self($fromTable ? $text . ' ' . self::TABLE_WARNING : $text, array_values($unique), $simulated, $fromTable);
    }

    /** Manuels lus, rien de pertinent pour la question. */
    public static function notFound(bool $simulated = false): self
    {
        return new self(self::NOT_FOUND, [], $simulated, false);
    }

    /** Aucun manuel exact ou de la série du modèle n'a de texte indexé : rien n'est lu, aucun modèle n'est appelé. */
    public static function notIndexed(): self
    {
        return new self(self::NOT_INDEXED, [], false, false);
    }

    public function isFound(): bool
    {
        return [] !== $this->citations;
    }
}
