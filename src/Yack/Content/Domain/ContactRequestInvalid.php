<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * A contact message refused by ContactRequest::submit(): `field` names the input at fault
 * (name, email, company, phone, subject, message), the message is shown to the visitor.
 */
final class ContactRequestInvalid extends \DomainException
{
    private function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function on(string $field, string $message): self
    {
        return new self($field, $message);
    }
}
