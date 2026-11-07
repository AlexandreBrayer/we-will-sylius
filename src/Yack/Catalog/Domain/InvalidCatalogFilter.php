<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** A catalogue filter value that cannot be applied (e.g. a power that is not a number). */
final class InvalidCatalogFilter extends \DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
