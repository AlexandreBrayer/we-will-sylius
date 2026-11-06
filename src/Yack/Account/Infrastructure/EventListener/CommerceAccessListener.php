<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\EventListener;

use App\Yack\Account\Domain\CommercePermission;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Server-side enforcement of the "only ECOM can order" rule: hiding the buttons is not enough.
 *
 * Twig shop: cart / checkout routes. Shop API: every cart, checkout and order resource
 * (anonymous → 401 through the JWT entry point, PRO → 403).
 */
final readonly class CommerceAccessListener
{
    private const RESTRICTED_ROUTE_PREFIXES = [
        'sylius_shop_cart_',
        'sylius_shop_checkout_',
    ];

    /** Shop API path segments (after /api/v2/shop/) of the cart / checkout / order resources. */
    private const RESTRICTED_API_SEGMENTS = [
        'orders',
        'account/orders',
        'adjustments',
        'order-items',
        'order-item-units',
        'payments',
        'shipments',
        'payment-requests',
    ];

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        #[Autowire(param: 'sylius.security.api_shop_route')]
        private string $apiShopRoute,
    ) {
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

    /** After the firewall (8), before API Platform reads the resource (ReadListener, 4). */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function onApiRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        $prefix = $this->apiShopRoute . '/';
        if (!str_starts_with($path, $prefix)) {
            return;
        }

        $segments = substr($path, strlen($prefix));
        foreach (self::RESTRICTED_API_SEGMENTS as $segment) {
            if ($segments === $segment || str_starts_with($segments, $segment . '/')) {
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
