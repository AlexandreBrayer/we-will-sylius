<?php

declare(strict_types=1);

namespace App\Yack\Steering\Infrastructure\Menu;

use App\Yack\Steering\Application\AlertBoard;
use App\Yack\Steering\Infrastructure\Security\SteeringVoter;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Back-office menu, entry « Tableau de bord », for the users allowed by SteeringVoter only: it stays active on the
 * tabs « SAV et pièces » and « Clients et encours » (routes of the dashboard), and carries the total of the alerts
 * « critique » and « à surveiller » of the three tabs (extra `yack_badge`, shown by the menu template when not zero).
 */
#[AsEventListener(event: 'sylius.menu.admin.main')]
final readonly class SteeringMenuListener
{
    public const TAB_ROUTES = ['app_yack_admin_steering_after_sales', 'app_yack_admin_steering_customers'];

    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private AlertBoard $alerts,
    ) {
    }

    public function __invoke(MenuBuilderEvent $event): void
    {
        $dashboard = $event->getMenu()->getChild('dashboard');
        if (null === $dashboard || !$this->authorization->isGranted(SteeringVoter::ATTRIBUTE)) {
            return;
        }

        $routes = $dashboard->getExtra('routes', []);
        $dashboard
            ->setExtra('routes', [...(\is_array($routes) ? $routes : []), ...array_map(static fn (string $route): array => ['route' => $route], self::TAB_ROUTES)])
            ->setExtra('yack_badge', $this->alerts->alerts()->total())
        ;
    }
}
