<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Customer\Customer;
use App\Yack\Account\Application\OrderAccessRequestData;
use App\Yack\Account\Domain\OrderAccessRequests;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<OrderAccessRequestView|OrderAccessRequestData>
 */
final readonly class OrderAccessRequestProvider implements ProviderInterface
{
    public const DRAFT_OPERATION = 'app_yack_api_shop_order_access_request_draft';

    public function __construct(
        private Security $security,
        private OrderAccessRequests $requests,
    ) {
    }

    /** @return list<OrderAccessRequestView>|OrderAccessRequestView|OrderAccessRequestData|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|OrderAccessRequestView|OrderAccessRequestData|null
    {
        $customer = self::customerOf($this->security);

        if ($operation instanceof CollectionOperationInterface) {
            return array_map(OrderAccessRequestView::fromRequest(...), $this->requests->allFor($customer));
        }

        if (self::DRAFT_OPERATION === $operation->getName()) {
            return OrderAccessRequestData::fromCustomer($customer);
        }

        $id = $uriVariables['id'] ?? null;
        $request = is_numeric($id) ? $this->requests->get((int) $id) : null;
        if (null === $request || $request->getCustomer() !== $customer) {
            return null;
        }

        return OrderAccessRequestView::fromRequest($request);
    }

    public static function customerOf(Security $security): Customer
    {
        $user = $security->getUser();
        $customer = $user instanceof ShopUserInterface ? $user->getCustomer() : null;
        if (!$customer instanceof Customer) {
            throw new AccessDeniedException();
        }

        return $customer;
    }
}
