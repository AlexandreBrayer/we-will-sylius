<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * A section refused at its save (422 on `field`, French message shown as is by the builder): its name, or
 * its blocks (malformed, bound to a template, or a free value the level of the author does not allow).
 */
final class CmsSectionRefused extends \DomainException
{
    private function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public static function name(string $message): self
    {
        return new self('name', $message);
    }

    public static function blocks(string $message): self
    {
        return new self('blocks', $message);
    }
}
