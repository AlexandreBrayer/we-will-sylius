<?php

declare(strict_types=1);

namespace App\Yack\Account\Application\Registration;

/**
 * Steps of the Yack sign-up (CDC §3.4), in order. The value is the URL segment.
 */
enum RegistrationStep: string
{
    case Profile = 'profil';
    case Company = 'societe';
    case Contact = 'contact';
    case Address = 'adresse';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Profil',
            self::Company => 'Société',
            self::Contact => 'Contact',
            self::Address => 'Adresses',
        };
    }

    public function position(): int
    {
        return (int) array_search($this, self::cases(), true) + 1;
    }

    public function next(): ?self
    {
        return self::cases()[$this->position()] ?? null;
    }

    public function previous(): ?self
    {
        return self::cases()[$this->position() - 2] ?? null;
    }
}
