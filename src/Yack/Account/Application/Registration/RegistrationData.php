<?php

declare(strict_types=1);

namespace App\Yack\Account\Application\Registration;

use App\Yack\Account\Domain\CustomerProfile;

/**
 * Validated sign-up data (built by the API from POST /api/v2/shop/yack/registrations).
 * The password is carried hashed only (see RegisterCustomer::hashPassword()).
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

    public function isPro(): bool
    {
        return CustomerProfile::PRO_GROUP_CODE === $this->profile;
    }
}
