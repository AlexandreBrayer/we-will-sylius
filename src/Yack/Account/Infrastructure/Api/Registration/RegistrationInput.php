<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api\Registration;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Yack\Account\Application\Registration\RegistrationData;
use App\Yack\Account\Domain\CustomerProfile;
use App\Yack\Account\Domain\Siret;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * POST /api/v2/shop/yack/registrations — Yack sign-up (same rules as the 4-step Twig form).
 * The account is created disabled; the activation e-mail links to {FRONT_URL}/activation/{token}.
 */
#[ApiResource(
    shortName: 'YackRegistration',
    operations: [
        new Post(
            uriTemplate: '/shop/yack/registrations',
            status: 201,
            output: RegistrationResult::class,
            processor: RegistrationProcessor::class,
            name: 'app_yack_api_shop_registration_post',
        ),
    ],
)]
final class RegistrationInput
{
    #[Assert\NotBlank(message: 'Choisissez votre profil.')]
    #[Assert\Choice(choices: [CustomerProfile::PRO_GROUP_CODE, CustomerProfile::ECOM_GROUP_CODE], message: 'Profil invalide : « pro » ou « ecom ».')]
    public ?string $profile = null;

    #[Assert\Valid]
    public CompanyInput $company;

    #[Assert\Valid]
    public ContactInput $contact;

    #[Assert\Valid]
    public AddressInput $billingAddress;

    /** Delivery address, when it differs from the billing one. */
    #[Assert\Valid]
    public ?AddressInput $shippingAddress = null;

    public function __construct()
    {
        $this->company = new CompanyInput();
        $this->contact = new ContactInput();
        $this->billingAddress = new AddressInput();
    }

    /** Raison sociale and SIRET required for PRO accounts; VAT number consistent with the SIRET. */
    #[Assert\Callback]
    public function validateCompany(ExecutionContextInterface $context): void
    {
        if (CustomerProfile::PRO_GROUP_CODE === $this->profile) {
            if (null === $this->company->name || '' === trim($this->company->name)) {
                $context->buildViolation('Indiquez la raison sociale de votre entreprise.')->atPath('company.name')->addViolation();
            }
            if (null === $this->company->siret || '' === trim($this->company->siret)) {
                $context->buildViolation('Le SIRET est obligatoire pour un compte professionnel.')->atPath('company.siret')->addViolation();
            }
        }

        $vatNumber = $this->company->vatNumber;
        if (null !== $vatNumber && '' !== $vatNumber && !Siret::isValidFrenchVatNumber($vatNumber, $this->company->siret)) {
            $context->buildViolation('N° de TVA invalide : format FR + clé (2 caractères) + SIREN (9 premiers chiffres du SIRET).')
                ->atPath('company.vatNumber')
                ->addViolation();
        }
    }

    public function toRegistrationData(string $passwordHash): RegistrationData
    {
        $data = new RegistrationData();
        $data->profile = $this->profile;
        $data->company = self::nullIfBlank($this->company->name);
        $data->siret = self::nullIfBlank($this->company->siret);
        $data->vatNumber = self::nullIfBlank($this->company->vatNumber);
        $data->firstName = $this->contact->firstName;
        $data->lastName = $this->contact->lastName;
        $data->email = $this->contact->email;
        $data->phoneNumber = $this->contact->phone;
        $data->passwordHash = $passwordHash;
        $data->billingStreet = $this->billingAddress->street;
        $data->billingPostcode = $this->billingAddress->postcode;
        $data->billingCity = $this->billingAddress->city;
        $data->billingCountryCode = $this->billingAddress->countryCode;
        $data->shippingSameAsBilling = null === $this->shippingAddress;
        if (null !== $this->shippingAddress) {
            $data->shippingStreet = $this->shippingAddress->street;
            $data->shippingPostcode = $this->shippingAddress->postcode;
            $data->shippingCity = $this->shippingAddress->city;
            $data->shippingCountryCode = $this->shippingAddress->countryCode;
        }

        return $data;
    }

    private static function nullIfBlank(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}
