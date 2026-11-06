<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

final class InvalidCmsTemplateAssignment extends \DomainException
{
    public function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }
}
