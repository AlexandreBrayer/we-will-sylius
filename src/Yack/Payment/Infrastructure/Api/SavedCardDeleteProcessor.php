<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Payment\Domain\Card\SavedCards;

/**
 * @implements ProcessorInterface<SavedCardView, null>
 */
final readonly class SavedCardDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private CustomerOrders $customerOrders,
        private SavedCards $savedCards,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $card = $this->savedCards->getFor($this->customerOrders->customer(), $data->id);
        if (null !== $card) {
            $this->savedCards->remove($card);
        }

        return null;
    }
}
