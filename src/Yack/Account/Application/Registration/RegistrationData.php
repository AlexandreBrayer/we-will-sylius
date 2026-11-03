<?php

declare(strict_types=1);

namespace App\Yack\Account\Application\Registration;

use App\Yack\Account\Domain\CustomerProfile;

/**
 * Sign-up data collected step by step (kept in session between steps).
 * The password is stored hashed only (see RegisterCustomer::hashPassword()).
 */
final class RegistrationData
{
    public ?string $profile = null;

    public ?string $company = null;

    public ?string $siret = null;

    public ?string $vatNumber = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $email = null;

    public ?string $phoneNumber = null;

    /** Form-only field, never stored. */
    public ?string $plainPassword = null;

    public ?string $passwordHash = null;

    public ?string $billingStreet = null;

    public ?string $billingPostcode = null;

    public ?string $billingCity = null;

    public ?string $billingCountryCode = 'FR';

    public bool $shippingSameAsBilling = true;

    public ?string $shippingStreet = null;

    public ?string $shippingPostcode = null;

    public ?string $shippingCity = null;

    public ?string $shippingCountryCode = 'FR';

    /** @var list<string> steps already validated */
    public array $completedSteps = [];

    public function isPro(): bool
    {
        return CustomerProfile::PRO_GROUP_CODE === $this->profile;
    }

    public function complete(RegistrationStep $step): void
    {
        if (!$this->isCompleted($step)) {
            $this->completedSteps[] = $step->value;
        }
    }

    public function isCompleted(RegistrationStep $step): bool
    {
        return in_array($step->value, $this->completedSteps, true);
    }

    /** First step not validated yet (the user cannot skip ahead). */
    public function firstIncompleteStep(): ?RegistrationStep
    {
        foreach (RegistrationStep::cases() as $step) {
            if (!$this->isCompleted($step)) {
                return $step;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        unset($data['plainPassword']);

        return $data;
    }

    /** @param array<mixed> $data session payload produced by toArray() */
    public static function fromArray(array $data): self
    {
        $registration = new self();
        foreach ($data as $property => $value) {
            if (is_string($property) && property_exists($registration, $property) && 'plainPassword' !== $property) {
                $registration->{$property} = $value;
            }
        }

        return $registration;
    }
}
