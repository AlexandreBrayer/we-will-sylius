<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use App\Entity\Customer\Customer;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Logged-in customer of the shop API and its placed orders (another customer's order is not found).
 */
final readonly class CustomerOrders
{
    /** @param OrderRepositoryInterface<OrderInterface> $orders */
    public function __construct(
        private Security $security,
        #[Autowire(service: 'sylius.repository.order')]
        private OrderRepositoryInterface $orders,
    ) {
    }

    public function customer(): Customer
    {
        $user = $this->security->getUser();
        $customer = $user instanceof ShopUserInterface ? $user->getCustomer() : null;
        if (!$customer instanceof Customer) {
            throw new AccessDeniedException();
        }

        return $customer;
    }

    public function placedOrder(mixed $tokenValue): OrderInterface
    {
        $customer = $this->customer();
        $order = is_string($tokenValue) ? $this->orders->findOneByTokenValue($tokenValue) : null;
        if (!$order instanceof OrderInterface || OrderInterface::STATE_CART === $order->getState() || $order->getCustomer() !== $customer) {
            throw new NotFoundHttpException('Commande introuvable.');
        }

        return $order;
    }
}
