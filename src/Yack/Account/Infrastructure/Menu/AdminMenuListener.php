<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $customers = $event->getMenu()->getChild('customers');
        if (null === $customers) {
            return;
        }

        $customers
            ->addChild('yack_order_access_requests', [
                'route' => 'app_yack_admin_order_access_request_index',
                'extras' => ['routes' => [['route' => 'app_yack_admin_order_access_request_show']]],
            ])
            ->setLabel('Demandes accès commande')
            ->setLabelAttribute('icon', 'tabler:heart-handshake')
        ;
    }
}
