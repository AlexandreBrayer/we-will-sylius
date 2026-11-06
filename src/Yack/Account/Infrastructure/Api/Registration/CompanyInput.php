<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api\Registration;

use App\Yack\Account\Infrastructure\Validator\ValidSiret;
use Symfony\Component\Validator\Constraints as Assert;

/** Company of the account: required for PRO (see RegistrationInput::validateCompany()), optional for ECOM. */
final class CompanyInput
{
    #[Assert\Length(max: 255, maxMessage: 'La raison sociale ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $name = null;

    #[ValidSiret]
    public ?string $siret = null;

    public ?string $vatNumber = null;
}
