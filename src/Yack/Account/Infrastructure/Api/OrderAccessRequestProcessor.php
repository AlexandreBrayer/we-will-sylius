<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Account\Application\OrderAccessRequestData;
use App\Yack\Account\Domain\OrderAccessRequests;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<OrderAccessRequestData, OrderAccessRequestView>
 */
final readonly class OrderAccessRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private OrderAccessRequests $requests,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderAccessRequestView
    {
        $customer = OrderAccessRequestProvider::customerOf($this->security);

        $latest = $this->requests->latestFor($customer);
        if (null !== $latest && $latest->isPending()) {
            throw new ConflictHttpException('Une demande d\'ouverture à la commande est déjà en cours d\'examen.');
        }

        $request = $data->toRequest($customer);
        $this->requests->add($request);

        return OrderAccessRequestView::fromRequest($request);
    }
}
