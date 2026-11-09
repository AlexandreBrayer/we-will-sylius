<?php

declare(strict_types=1);

namespace App\Yack\Backoffice\Infrastructure\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Pictogrammes des groupes du menu du back-office, ceux de la maquette « Admin Sylius » (Figma YACK Seduction, nœud 23:3).
 * Passe après les autres écouteurs du menu (priorité -10) : « Contenu », ajouté par le contexte Content, est déjà là.
 */
#[AsEventListener(event: 'sylius.menu.admin.main', priority: -10)]
final class AdminMenuIconListener
{
    /** Nom de l'entrée de premier niveau → pictogramme Tabler. */
    private const array ICONS = [
        'dashboard' => 'tabler:dashboard',
        'catalog' => 'tabler:book',
        'sales' => 'tabler:shopping-bag',
        'customers' => 'tabler:user',
        'marketing' => 'tabler:percentage',
        'configuration' => 'tabler:adjustments',
        'official_support' => 'tabler:info-circle',
        'sylius.ui.administration' => 'tabler:lock',
        'yack_content' => 'tabler:list',
    ];

    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        foreach (self::ICONS as $name => $icon) {
            $menu->getChild($name)?->setLabelAttribute('icon', $icon);
        }
    }
}
