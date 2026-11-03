<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Twig;

use App\Entity\Account\OrderAccessRequest;
use App\Entity\Customer\Customer;
use App\Yack\Account\Domain\CustomerProfile;
use App\Yack\Account\Domain\OrderAccessRequests;
use App\Yack\Account\Infrastructure\Security\CustomerProfileResolver;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CustomerProfileExtension extends AbstractExtension
{
    /** @var array<int, OrderAccessRequest|null> latest request per customer id, for the current request */
    private array $orderAccessRequests = [];

    public function __construct(
        private readonly CustomerProfileResolver $profileResolver,
        private readonly OrderAccessRequests $requests,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_customer_profile', $this->currentProfile(...)),
            new TwigFunction('yack_order_access_request', $this->currentOrderAccessRequest(...)),
        ];
    }

    public function currentProfile(): CustomerProfile
    {
        return $this->profileResolver->current();
    }

    /** Latest "demande d'ouverture à la commande" of the logged-in customer, if any. */
    public function currentOrderAccessRequest(): ?OrderAccessRequest
    {
        $user = $this->security->getUser();
        $customer = $user instanceof ShopUserInterface ? $user->getCustomer() : null;
        if (!$customer instanceof Customer) {
            return null;
        }

        $customerId = $customer->getId();
        if (!is_int($customerId)) {
            return null;
        }

        if (!array_key_exists($customerId, $this->orderAccessRequests)) {
            $this->orderAccessRequests[$customerId] = $this->requests->latestFor($customer);
        }

        return $this->orderAccessRequests[$customerId];
    }
}
