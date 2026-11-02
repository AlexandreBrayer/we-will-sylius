<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** Raised when a record coming from Yack cannot be applied; the sync skips it and goes on. */
final class InvalidSourceProduct extends \DomainException
{
}
