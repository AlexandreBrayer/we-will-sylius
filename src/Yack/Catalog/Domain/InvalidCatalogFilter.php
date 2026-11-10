<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** A catalogue filter value that cannot be applied (e.g. an unknown power band); `field`: facet code or parameter. */
final class InvalidCatalogFilter extends \DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
