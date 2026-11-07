<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/** Document (ou produit) refusé au chargement de la base ; `manualId` = id du document en cause, si connu. */
final class InvalidManual extends \InvalidArgumentException
{
    public function __construct(public readonly ?string $manualId, string $message)
    {
        parent::__construct($message);
    }

    public static function document(string $manualId, string $reason): self
    {
        return new self($manualId, sprintf('Document « %s » refusé : %s.', $manualId, $reason));
    }

    public static function library(string $reason): self
    {
        return new self(null, sprintf('Base de manuels illisible : %s.', $reason));
    }
}
