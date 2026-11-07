<?php

declare(strict_types=1);

namespace App\Yack\Common\Domain;

/** The real adapter is selected but its configuration is incomplete (raised at the first call). */
final class AiMisconfigured extends \LogicException
{
    public static function missing(string $variable): self
    {
        return new self(sprintf('Configuration IA incomplète : %s est vide (à renseigner dans .env.local).', $variable));
    }
}
