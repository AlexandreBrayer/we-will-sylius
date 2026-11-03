<?php

declare(strict_types=1);

namespace App\Yack\Account\Application;

use App\Entity\Account\OrderAccessRequest;
use App\Yack\Account\Domain\CustomerProfile;
use App\Yack\Account\Domain\OrderAccessNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Back-office decision on a PRO "order access" request. Accepting moves the customer to the
 * ECOM group (prices and ordering available on their next page view) and e-mails them.
 */
final readonly class OrderAccessDecision
{
    /** @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository */
    public function __construct(
        #[Autowire(service: 'sylius.repository.customer_group')]
        private RepositoryInterface $customerGroupRepository,
        private EntityManagerInterface $entityManager,
        private OrderAccessNotifier $notifier,
    ) {
    }

    public function accept(OrderAccessRequest $request): void
    {
        $ecomGroup = $this->customerGroupRepository->findOneBy(['code' => CustomerProfile::ECOM_GROUP_CODE]);
        if (!$ecomGroup instanceof CustomerGroupInterface) {
            throw new \LogicException(sprintf('Customer group "%s" does not exist.', CustomerProfile::ECOM_GROUP_CODE));
        }

        $request->accept(new \DateTimeImmutable());
        $request->getCustomer()->setGroup($ecomGroup);
        $this->entityManager->flush();
        $this->notifier->notifyGranted($request);
    }

    public function refuse(OrderAccessRequest $request): void
    {
        $request->refuse(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
