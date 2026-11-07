<?php

declare(strict_types=1);

namespace App\Yack\Common\Domain;

/** The intelligent function cannot answer now: each context turns it into a clear message. */
final class AiUnavailable extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self('Service IA indisponible : '.$reason);
    }
}
