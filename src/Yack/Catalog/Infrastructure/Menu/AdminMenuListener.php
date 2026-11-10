<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Back-office: Catalogue > Synchronisation PIM/ERP (dashboard, runs, manual import). */
#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $catalog = $event->getMenu()->getChild('catalog');
        if (null === $catalog) {
            return;
        }

        $catalog
            ->addChild('yack_catalog_sync', [
                'route' => 'app_yack_admin_catalog_sync_index',
                'extras' => ['routes' => [
                    ['route' => 'app_yack_admin_catalog_sync_show'],
                    ['route' => 'app_yack_admin_catalog_sync_import'],
                    ['route' => 'app_yack_admin_catalog_sync_import_preview'],
                ]],
            ])
            ->setLabel('Synchronisation PIM/ERP')
            ->setLabelAttribute('icon', 'tabler:refresh')
        ;
    }
}
