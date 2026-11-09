<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Persistence;

use App\Entity\Customer\Customer;
use App\Entity\Payment\SavedCard;
use App\Yack\Payment\Domain\Card\SavedCards;
use App\Yack\Payment\Domain\Card\WalletCard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SavedCards::class)]
final readonly class DoctrineSavedCards implements SavedCards
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Customer $customer, WalletCard $walletCard): SavedCard
    {
        foreach ($this->allFor($customer) as $saved) {
            if ($saved->isSameCardAs($walletCard->card)) {
                $saved->renew($walletCard);
                $this->entityManager->flush();

                return $saved;
            }
        }

        $saved = new SavedCard($customer, $walletCard);
        $this->entityManager->persist($saved);
        $this->entityManager->flush();

        return $saved;
    }

    public function allFor(Customer $customer): array
    {
        return $this->entityManager->getRepository(SavedCard::class)
            ->findBy(['customer' => $customer], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function getFor(Customer $customer, int $id): ?SavedCard
    {
        return $this->entityManager->getRepository(SavedCard::class)->findOneBy(['id' => $id, 'customer' => $customer]);
    }

    public function remove(SavedCard $card): void
    {
        $this->entityManager->remove($card);
        $this->entityManager->flush();
    }
}
