<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Persistence;

use App\Entity\Customer\Customer;
use App\Yack\Account\Domain\CustomerProfile;
use App\Yack\Account\Domain\ProSignups;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProSignups::class)]
final readonly class DoctrineProSignups implements ProSignups
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function latest(int $limit): array
    {
        /** @var list<Customer> $customers */
        $customers = $this->proCustomers()
            ->orderBy('customer.createdAt', 'DESC')
            ->addOrderBy('customer.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $customers;
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->proCustomers()
            ->select('COUNT(customer.id)')
            ->andWhere('customer.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function proCustomers(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('customer')
            ->from(Customer::class, 'customer')
            ->innerJoin('customer.group', 'customerGroup')
            ->andWhere('customerGroup.code = :proGroup')
            ->setParameter('proGroup', CustomerProfile::PRO_GROUP_CODE);
    }
}
