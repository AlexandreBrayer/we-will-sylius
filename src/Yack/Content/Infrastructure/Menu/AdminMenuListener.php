<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Back-office menu: "Contenu > Builder, Pages, Templates" (the SvelteKit builder, opened with the admin session, and the CMS pages and page templates it edits). */
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
            ->addChild('yack_cms_builder', ['route' => 'app_yack_admin_builder'])
            ->setLabel('Ouvrir le builder')
            ->setLabelAttribute('icon', 'tabler:layout-dashboard')
        ;

        $content
            ->addChild('yack_cms_pages', ['route' => 'app_yack_admin_cms_page_index'])
            ->setLabel('Pages')
            ->setLabelAttribute('icon', 'tabler:file-text')
        ;

        $content
            ->addChild('yack_cms_templates', ['route' => 'app_yack_admin_cms_template_index'])
            ->setLabel('Templates')
            ->setLabelAttribute('icon', 'tabler:template')
        ;
    }
}
