<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Application;

use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualAnswer;
use App\Yack\Assistance\Domain\ManualAnswerer;
use App\Yack\Assistance\Domain\ManualLibrary;
use App\Yack\Assistance\Domain\ManualMatch;
use App\Yack\Assistance\Domain\ManualMatcher;
use App\Yack\Assistance\Domain\ModelReference;
use App\Yack\Common\Domain\AiUnavailable;

/**
 * Question aux manuels d'un modèle : la référence est lue comme à la recherche (`ModelReference::fromInput`),
 * `ManualMatcher` donne ses documents, et seuls ceux de niveau exact ou série (`MatchLevel::isReliable()`) qui ont
 * un texte indexé sont lus. Aucun : « aucun manuel indexé », sans appel à l'answerer. Les niveaux famille et
 * aucun ne nourrissent jamais une réponse.
 *
 * Bornes : question de 3 à 300 caractères (espaces regroupées), référence de 1 à 40 caractères.
 */
final readonly class AskManuals
{
    public const QUESTION_MIN_LENGTH = 3;
    public const QUESTION_MAX_LENGTH = 300;
    public const REFERENCE_MAX_LENGTH = ModelReference::MAX_LENGTH;

    public const QUESTION_TOO_SHORT = 'Votre question doit comporter au moins 3 caractères.';
    public const QUESTION_TOO_LONG = 'Votre question ne doit pas dépasser 300 caractères.';
    public const REFERENCE_REQUIRED = 'Référence obligatoire.';
    public const REFERENCE_TOO_LONG = 'Référence trop longue : 40 caractères au plus.';

    public function __construct(
        private ManualLibrary $library,
        private ManualAnswerer $answerer,
    ) {
    }

    /**
     * Champs refusés et leur message (vide : la saisie est recevable).
     *
     * @return array<'question'|'reference', string>
     */
    public static function problems(string $question, string $reference): array
    {
        $problems = [];
        $length = mb_strlen(self::clean($question));
        if ($length < self::QUESTION_MIN_LENGTH) {
            $problems['question'] = self::QUESTION_TOO_SHORT;
        } elseif ($length > self::QUESTION_MAX_LENGTH) {
            $problems['question'] = self::QUESTION_TOO_LONG;
        }

        $reference = trim($reference);
        if (mb_strlen($reference) > self::REFERENCE_MAX_LENGTH) {
            $problems['reference'] = self::REFERENCE_TOO_LONG;
        } elseif (null === ModelReference::fromInput($reference)) {
            $problems['reference'] = self::REFERENCE_REQUIRED;
        }

        return $problems;
    }

    /**
     * @throws \InvalidArgumentException saisie refusée par `problems()`
     * @throws AiUnavailable
     */
    public function __invoke(string $question, string $reference): ManualAnswer
    {
        $problems = self::problems($question, $reference);
        $model = ModelReference::fromInput($reference);
        if ([] !== $problems || null === $model) {
            throw new \InvalidArgumentException(implode(' ', $problems));
        }

        $manuals = $this->readable($model);
        if ([] === $manuals) {
            return ManualAnswer::notIndexed();
        }

        return $this->answerer->answer(self::clean($question), $manuals);
    }

    /**
     * Documents exact et série du modèle qui ont un texte indexé, dans l'ordre du matcher.
     *
     * @return list<Manual>
     */
    public function readable(ModelReference $model): array
    {
        $matches = (new ManualMatcher())->match($model, $this->library->documents(), $this->library->products());

        return array_values(array_map(
            static fn (ManualMatch $match): Manual => $match->manual,
            array_filter($matches, static fn (ManualMatch $match): bool => $match->level->isReliable() && $match->manual->hasText()),
        ));
    }

    private static function clean(string $question): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $question));
    }
}
