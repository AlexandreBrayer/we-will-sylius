<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Security;

use App\Yack\Account\Domain\CustomerProfile;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Maps the authenticated Sylius shop user (customer group) to the Yack commercial profile.
 */
final readonly class CustomerProfileResolver
{
    public function __construct(private Security $security)
    {
    }

    public function current(): CustomerProfile
    {
        return $this->forUser($this->security->getUser());
    }

    public function forUser(?UserInterface $user): CustomerProfile
    {
        if (!$user instanceof ShopUserInterface) {
            return CustomerProfile::Visitor;
        }

        $customer = $user->getCustomer();
        if (null === $customer) {
            return CustomerProfile::Visitor;
        }

        return CustomerProfile::fromCustomerGroupCode($customer->getGroup()?->getCode());
    }
}
