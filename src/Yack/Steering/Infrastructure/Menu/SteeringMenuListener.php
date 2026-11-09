<?php

declare(strict_types=1);

namespace App\Yack\Steering\Infrastructure\Menu;

use App\Yack\Steering\Application\AlertBoard;
use App\Yack\Steering\Domain\SteeringPage;
use App\Yack\Steering\Infrastructure\Security\SteeringVoter;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Back-office menu: group « Direction » at the very top (Pilotage, SAV et pièces, Clients et encours), for the
 * administrator only (SteeringVoter). Each entry carries the number of its alerts « critique » and « à surveiller »
 * (extra `yack_badge`, shown by the menu template when it is not zero).
 */
#[AsEventListener(event: 'sylius.menu.admin.main')]
final readonly class SteeringMenuListener
{
    public const GROUP = 'yack_direction';

    public const ROUTES = [
        'pilotage' => 'app_yack_admin_steering_pilotage',
        'sav-et-pieces' => 'app_yack_admin_steering_after_sales',
        'clients-et-encours' => 'app_yack_admin_steering_customers',
    ];

    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private AlertBoard $alerts,
    ) {
    }

    public function __invoke(MenuBuilderEvent $event): void
    {
        if (!$this->authorization->isGranted(SteeringVoter::ATTRIBUTE)) {
            return;
        }

        $menu = $event->getMenu();
        $group = $menu
            ->addChild(self::GROUP)
            ->setLabel('Direction')
            ->setLabelAttribute('icon', 'tabler:compass')
        ;

        $alerts = $this->alerts->alerts();
        foreach (SteeringPage::cases() as $page) {
            $group
                ->addChild(self::GROUP.'_'.$page->value, ['route' => self::ROUTES[$page->value]])
                ->setLabel($page->label())
                ->setExtra('yack_badge', $alerts->badge($page))
            ;
        }

        $others = array_values(array_diff(array_keys($menu->getChildren()), [self::GROUP]));
        $menu->reorderChildren([self::GROUP, ...$others]);
    }
}
