<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/** An answer was built without any source: refused, the assistant never answers from nowhere. */
final class AnswerWithoutSource extends \DomainException
{
    public static function forText(string $text): self
    {
        return new self(sprintf('Réponse sans source refusée : « %s ».', mb_substr($text, 0, 80)));
    }
}
