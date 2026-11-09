<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Payment\Application\Card\StartCardPayment;
use App\Yack\Payment\Domain\Card\CardPaymentNotAllowed;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<CardPaymentStartInput, CardPaymentRedirect>
 */
final readonly class StartCardPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private CustomerOrders $customerOrders,
        private StartCardPayment $startCardPayment,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CardPaymentRedirect
    {
        $order = $this->customerOrders->placedOrder($uriVariables['tokenValue'] ?? null);

        try {
            $session = $this->startCardPayment->start($order, $this->customerOrders->customer(), $data->savedCardId);
        } catch (CardPaymentNotAllowed $notAllowed) {
            throw new ConflictHttpException($notAllowed->getMessage(), $notAllowed);
        }

        return CardPaymentRedirect::of($session);
    }
}
