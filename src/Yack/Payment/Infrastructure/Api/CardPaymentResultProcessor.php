<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Payment\Application\Card\ConcludeCardPayment;
use App\Yack\Payment\Domain\Card\CardPaymentNotAllowed;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<null, CardPaymentOutcome>
 */
final readonly class CardPaymentResultProcessor implements ProcessorInterface
{
    public function __construct(
        private CustomerOrders $customerOrders,
        private ConcludeCardPayment $concludeCardPayment,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CardPaymentOutcome
    {
        $order = $this->customerOrders->placedOrder($uriVariables['tokenValue'] ?? null);

        try {
            $attempt = $this->concludeCardPayment->conclude($order);
        } catch (CardPaymentNotAllowed $notAllowed) {
            throw new ConflictHttpException($notAllowed->getMessage(), $notAllowed);
        }

        return CardPaymentOutcome::of((string) $order->getTokenValue(), $attempt);
    }
}
