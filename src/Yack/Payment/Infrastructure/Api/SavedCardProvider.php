<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Payment\Domain\Card\SavedCards;
use Psr\Clock\ClockInterface;

/**
 * @implements ProviderInterface<SavedCardView>
 */
final readonly class SavedCardProvider implements ProviderInterface
{
    public function __construct(
        private CustomerOrders $customerOrders,
        private SavedCards $savedCards,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<SavedCardView>|SavedCardView|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|SavedCardView|null
    {
        $customer = $this->customerOrders->customer();
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if ($operation instanceof CollectionOperationInterface) {
            return array_map(static fn ($card): SavedCardView => SavedCardView::of($card, $now), $this->savedCards->allFor($customer));
        }

        $id = $uriVariables['id'] ?? null;
        $card = is_numeric($id) ? $this->savedCards->getFor($customer, (int) $id) : null;

        return null === $card ? null : SavedCardView::of($card, $now);
    }
}
