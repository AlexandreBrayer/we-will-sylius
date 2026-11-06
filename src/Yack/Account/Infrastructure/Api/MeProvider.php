<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Customer\Customer;
use App\Yack\Account\Domain\OrderAccessRequests;
use App\Yack\Account\Infrastructure\Security\CustomerProfileResolver;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Me>
 */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private CustomerProfileResolver $profileResolver,
        private OrderAccessRequests $orderAccessRequests,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Me
    {
        $user = $this->security->getUser();
        $me = new Me($this->profileResolver->forUser($user));

        $customer = $user instanceof ShopUserInterface ? $user->getCustomer() : null;
        if (!$customer instanceof Customer) {
            return $me;
        }

        $me->authenticated = true;
        $me->email = $customer->getEmail();
        $me->firstName = $customer->getFirstName();
        $me->lastName = $customer->getLastName();
        $me->phoneNumber = $customer->getPhoneNumber();
        $me->company = $customer->getCompany();
        $me->siret = $customer->getSiret();
        $me->vatNumber = $customer->getVatNumber();

        $address = $customer->getDefaultAddress();
        if (null !== $address) {
            $me->billingAddress = [
                'company' => $address->getCompany(),
                'street' => $address->getStreet(),
                'postcode' => $address->getPostcode(),
                'city' => $address->getCity(),
                'countryCode' => $address->getCountryCode(),
            ];
        }

        $request = $this->orderAccessRequests->latestFor($customer);
        if (null !== $request) {
            $me->orderAccessRequest = [
                'status' => $request->getStatus()->value,
                'statusLabel' => $request->getStatus()->label(),
                'createdAt' => $request->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'decidedAt' => $request->getDecidedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $me;
    }
}
