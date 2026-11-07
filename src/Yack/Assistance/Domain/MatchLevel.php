<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/** Niveau de correspondance d'un document avec la référence, du plus sûr au moins sûr. */
enum MatchLevel: string
{
    case Exact = 'exact';
    case Series = 'serie';
    case Family = 'famille';
    case None = 'aucun';

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /** Les niveaux famille et aucun ne nourrissent jamais une réponse aux questions. */
    public function isReliable(): bool
    {
        return self::Exact === $this || self::Series === $this;
    }
}
