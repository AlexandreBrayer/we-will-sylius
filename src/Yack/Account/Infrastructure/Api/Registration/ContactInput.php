<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api\Registration;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactInput
{
    #[Assert\NotBlank(message: 'Indiquez votre prénom.')]
    #[Assert\Length(max: 255)]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    #[Assert\Length(max: 255)]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'Indiquez votre adresse e-mail.')]
    #[Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Indiquez un numéro de téléphone.')]
    #[Assert\Regex(pattern: '/^\+?[0-9 .()-]{10,20}$/', message: 'Numéro de téléphone invalide (10 chiffres attendus).')]
    public ?string $phone = null;

    #[Assert\NotBlank(message: 'Choisissez un mot de passe.')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit comporter au moins {{ limit }} caractères.')]
    public ?string $password = null;
}
