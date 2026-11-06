<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Back-office menu: "Contenu > Pages" (CMS pages edited in the SvelteKit builder). */
#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $content = $event->getMenu()
            ->addChild('yack_content')
            ->setLabel('Contenu')
            ->setLabelAttribute('icon', 'tabler:layout-board')
        ;

        $content
            ->addChild('yack_cms_pages', ['route' => 'app_yack_admin_cms_page_index'])
            ->setLabel('Pages')
            ->setLabelAttribute('icon', 'tabler:file-text')
        ;
    }
}
