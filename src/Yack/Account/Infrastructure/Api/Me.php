<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Yack\Account\Domain\CustomerProfile;

/**
 * GET /api/v2/shop/yack/me — who is browsing and what they may do (prices, ordering).
 * Public: anonymous callers get the "visitor" profile; with a shop JWT, the customer's.
 */
#[ApiResource(
    shortName: 'YackMe',
    operations: [
        new Get(
            uriTemplate: '/shop/yack/me',
            provider: MeProvider::class,
            name: 'app_yack_api_shop_me_get',
        ),
    ],
)]
final class Me
{
    public bool $authenticated = false;

    public string $profile = CustomerProfile::Visitor->value;

    public string $profileLabel;

    public bool $canSeePrices = false;

    public bool $canOrder = false;

    public bool $canRequestOrderAccess = false;

    public ?string $email = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $phoneNumber = null;

    public ?string $company = null;

    public ?string $siret = null;

    public ?string $vatNumber = null;

    /** @var array{company: ?string, street: ?string, postcode: ?string, city: ?string, countryCode: ?string}|null */
    public ?array $billingAddress = null;

    /**
     * Latest PRO → ECOM "order access" request, if any.
     *
     * @var array{status: string, statusLabel: string, createdAt: string, decidedAt: ?string}|null
     */
    public ?array $orderAccessRequest = null;

    public function __construct(CustomerProfile $profile)
    {
        $this->profile = $profile->value;
        $this->profileLabel = $profile->label();
        $this->canSeePrices = $profile->canSeePrices();
        $this->canOrder = $profile->canPlaceOrder();
        $this->canRequestOrderAccess = $profile->canRequestOrderAccess();
    }
}
