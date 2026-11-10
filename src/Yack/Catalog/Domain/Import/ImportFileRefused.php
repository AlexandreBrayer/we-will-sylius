<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

/** A file the manual import does not take (size, format, content); the message is shown as is. */
final class ImportFileRefused extends \DomainException
{
}
