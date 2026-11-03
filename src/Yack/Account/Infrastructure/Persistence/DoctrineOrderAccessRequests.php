<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Persistence;

use App\Entity\Account\OrderAccessRequest;
use App\Entity\Customer\Customer;
use App\Yack\Account\Domain\OrderAccessRequests;
use App\Yack\Account\Domain\OrderAccessRequestStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(OrderAccessRequests::class)]
final readonly class DoctrineOrderAccessRequests implements OrderAccessRequests
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(OrderAccessRequest $request): void
    {
        $this->entityManager->persist($request);
        $this->entityManager->flush();
    }

    public function get(int $id): ?OrderAccessRequest
    {
        return $this->entityManager->find(OrderAccessRequest::class, $id);
    }

    public function latestFor(Customer $customer): ?OrderAccessRequest
    {
        return $this->entityManager->getRepository(OrderAccessRequest::class)
            ->findOneBy(['customer' => $customer], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function allFor(Customer $customer): array
    {
        return $this->entityManager->getRepository(OrderAccessRequest::class)
            ->findBy(['customer' => $customer], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function allNewestFirst(?OrderAccessRequestStatus $status = null): array
    {
        $criteria = null === $status ? [] : ['status' => $status];

        return $this->entityManager->getRepository(OrderAccessRequest::class)
            ->findBy($criteria, ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function countPending(): int
    {
        return $this->entityManager->getRepository(OrderAccessRequest::class)
            ->count(['status' => OrderAccessRequestStatus::Pending]);
    }
}
