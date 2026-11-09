<?php

declare(strict_types=1);

namespace App\Yack\Content\Application;

/**
 * The content changed between the preview of a bulk style replacement and its confirmation: the number
 * of blocks holding the value is no longer the one the administrator confirmed. Nothing is replaced.
 */
final class StyleReplacementOutdated extends \RuntimeException
{
    public function __construct(public readonly int $expected, public readonly int $actual)
    {
        parent::__construct(sprintf(
            'Le nombre de blocs concernés a changé depuis l\'aperçu (%d au lieu de %d) : rien n\'a été remplacé. Vérifiez puis confirmez à nouveau.',
            $actual,
            $expected,
        ));
    }
}
