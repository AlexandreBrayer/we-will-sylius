<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\EventListener;

use App\Yack\Account\Domain\CommercePermission;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Server-side enforcement of the "only ECOM can order" rule on the shop API: every cart,
 * checkout and order resource (anonymous → 401 through the JWT entry point, PRO → 403).
 */
final readonly class CommerceAccessListener
{
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
