<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** A bulk style replacement (StyleReplacement) that cannot be done; the message is shown as is. */
final class InvalidStyleReplacement extends \DomainException
{
}
