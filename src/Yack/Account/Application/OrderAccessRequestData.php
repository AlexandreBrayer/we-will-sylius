<?php

declare(strict_types=1);

namespace App\Yack\Account\Application;

use App\Entity\Account\OrderAccessRequest;
use App\Entity\Customer\Customer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form model of the "demande d'ouverture à la commande", prefilled from the customer account.
 */
final class OrderAccessRequestData
{
    #[Assert\NotBlank(message: 'Indiquez la raison sociale.')]
    #[Assert\Length(max: 255)]
    public ?string $company = null;

    #[Assert\NotBlank(message: 'Indiquez le SIRET.')]
    #[Assert\Regex(pattern: '/^\d{14}$/', message: 'Le SIRET comporte 14 chiffres.', normalizer: [self::class, 'normalizeSiret'])]
    public ?string $siret = null;

    #[Assert\NotBlank(message: 'Indiquez vos nom et prénom.')]
    #[Assert\Length(max: 255)]
    public ?string $fullName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Indiquez un téléphone.')]
    #[Assert\Length(max: 32)]
    public ?string $phone = null;

    #[Assert\NotBlank(message: 'Indiquez l\'adresse de l\'entreprise.')]
    #[Assert\Length(max: 1000)]
    public ?string $address = null;

    #[Assert\Length(max: 2000)]
    public ?string $message = null;

    public static function fromCustomer(Customer $customer): self
    {
        $data = new self();
        $data->company = $customer->getCompany() ?? $customer->getDefaultAddress()?->getCompany();
        $data->siret = $customer->getSiret();
        $data->fullName = trim(sprintf('%s %s', $customer->getFirstName() ?? '', $customer->getLastName() ?? '')) ?: null;
        $data->email = $customer->getEmail();
        $data->phone = $customer->getPhoneNumber() ?? $customer->getDefaultAddress()?->getPhoneNumber();

        $address = $customer->getDefaultAddress();
        if (null !== $address) {
            $data->address = sprintf("%s\n%s %s", $address->getStreet() ?? '', $address->getPostcode() ?? '', $address->getCity() ?? '');
        }

        return $data;
    }

    public static function normalizeSiret(string $siret): string
    {
        return preg_replace('/\s+/', '', $siret) ?? $siret;
    }

    public function toRequest(Customer $customer): OrderAccessRequest
    {
        return new OrderAccessRequest(
            $customer,
            trim((string) $this->company),
            self::normalizeSiret((string) $this->siret),
            trim((string) $this->fullName),
            trim((string) $this->email),
            trim((string) $this->phone),
            trim((string) $this->address),
            '' === trim((string) $this->message) ? null : trim((string) $this->message),
        );
    }
}
