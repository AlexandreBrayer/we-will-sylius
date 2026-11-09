<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\User\AdminUser;

/**
 * Administrator who saved a revision, copied at the time of the save: a renamed or deleted account changes
 * nothing in the history. `name` is what the history shows: « Prénom Nom », or the e-mail when the account
 * has neither first nor last name.
 */
final readonly class CmsRevisionAuthor
{
    public function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public string $name,
    ) {
    }

    public static function of(AdminUser $user): self
    {
        $firstName = self::clean($user->getFirstName());
        $lastName = self::clean($user->getLastName());
        // Each name fits 255 characters, the two together may not: `author_name` is 255 as well.
        $name = trim(mb_substr(trim(($firstName ?? '') . ' ' . ($lastName ?? '')), 0, 255));

        return new self($firstName, $lastName, '' !== $name ? $name : (self::clean($user->getEmail()) ?? 'Administrateur'));
    }

    /** Stored columns back to an author; null when the revision has none (fixtures, commands). */
    public static function stored(?string $firstName, ?string $lastName, ?string $name): ?self
    {
        return null === $name ? null : new self($firstName, $lastName, $name);
    }

    private static function clean(?string $value): ?string
    {
        $value = null === $value ? '' : mb_substr(trim($value), 0, 255);

        return '' === $value ? null : $value;
    }
}
