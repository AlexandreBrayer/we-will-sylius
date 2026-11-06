<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api\Registration;

use Symfony\Component\Validator\Constraints as Assert;

final class AddressInput
{
    #[Assert\NotBlank(message: 'Indiquez l\'adresse (numéro et voie).')]
    #[Assert\Length(max: 255)]
    public ?string $street = null;

    #[Assert\NotBlank(message: 'Indiquez le code postal.')]
    #[Assert\Length(max: 10)]
    public ?string $postcode = null;

    #[Assert\NotBlank(message: 'Indiquez la ville.')]
    #[Assert\Length(max: 255)]
    public ?string $city = null;

    #[Assert\NotBlank(message: 'Choisissez un pays.')]
    #[Assert\Country(message: 'Pays invalide.')]
    public ?string $countryCode = 'FR';
}
