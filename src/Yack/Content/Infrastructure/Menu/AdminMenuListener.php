<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Menu;

use App\Yack\Content\Infrastructure\Security\StyleDeviationVoter;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Back-office menu: "Contenu > Pages du site (builder), Pages, Templates, Écarts de charte" (the SvelteKit
 * builder, opened with the admin session, the CMS pages and page templates it edits, and the report of
 * the style values outside the charte, shown to the administrators allowed to open it).
 */
#[AsEventListener(event: 'sylius.menu.admin.main')]
final readonly class AdminMenuListener
{
    public function __construct(private AuthorizationCheckerInterface $authorization)
    {
    }

    public function __invoke(MenuBuilderEvent $event): void
    {
        $content = $event->getMenu()
            ->addChild('yack_content')
            ->setLabel('Contenu')
            ->setLabelAttribute('icon', 'tabler:layout-board')
        ;

        $content
            ->addChild('yack_cms_builder', ['route' => 'app_yack_admin_builder'])
            ->setLabel('Pages du site (builder)')
            ->setLabelAttribute('icon', 'tabler:layout-dashboard')
            ->setLinkAttribute('target', '_blank')
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

        if ($this->authorization->isGranted(StyleDeviationVoter::ATTRIBUTE)) {
            $content
                ->addChild('yack_style_deviations', ['route' => 'app_yack_admin_style_deviation_index'])
                ->setLabel('Écarts de charte')
                ->setLabelAttribute('icon', 'tabler:palette')
            ;
        }
    }
}
