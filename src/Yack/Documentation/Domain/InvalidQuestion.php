<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Domain;

/** The question cannot be asked (empty, too long, bad product code); `field` = input property. */
final class InvalidQuestion extends \InvalidArgumentException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
