<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\EventListener;

use App\Yack\Account\Domain\CommercePermission;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Server-side enforcement of the "only ECOM can order" rule: hiding the buttons is not enough.
 */
final readonly class CommerceAccessListener
{
    private const RESTRICTED_ROUTE_PREFIXES = [
        'sylius_shop_cart_',
        'sylius_shop_checkout_',
    ];

    public function __construct(private AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    /** Every add-to-cart (product page live component or SAV exploded view) goes through this event. */
    #[AsEventListener(event: SyliusCartEvents::CART_ITEM_ADD, priority: 1024)]
    public function onCartItemAdd(GenericEvent $event): void
    {
        $this->denyUnlessCanOrder();
    }

    /** Runs after the firewall (priority 8) so the shop user is known. */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->getString('_route');
        foreach (self::RESTRICTED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                $this->denyUnlessCanOrder();

                return;
            }
        }
    }

    private function denyUnlessCanOrder(): void
    {
        if (!$this->authorizationChecker->isGranted(CommercePermission::PlaceOrder->value)) {
            throw new AccessDeniedException('La commande en ligne est réservée aux comptes ECOM.');
        }
    }
}
