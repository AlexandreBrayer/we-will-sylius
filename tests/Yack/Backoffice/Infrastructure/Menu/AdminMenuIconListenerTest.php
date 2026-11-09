<?php

declare(strict_types=1);

namespace App\Tests\Yack\Backoffice\Infrastructure\Menu;

use App\Yack\Backoffice\Infrastructure\Menu\AdminMenuIconListener;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/**
 * @internal
 */
#[CoversClass(AdminMenuIconListener::class)]
final class AdminMenuIconListenerTest extends TestCase
{
    #[DataProvider('menuGroups')]
    public function testEachGroupOfTheMenuGetsTheIconOfTheMockup(string $name, string $icon): void
    {
        $menu = $this->menuWith(['dashboard', 'catalog', 'sales', 'customers', 'marketing', 'configuration', 'official_support', 'sylius.ui.administration', 'yack_content']);

        $this->assertSame($icon, $menu->getChild($name)?->getLabelAttribute('icon'));
    }

    public function testTheSubItemsKeepTheirOwnIcon(): void
    {
        $menu = $this->menuWith(['customers']);
        $group = $menu->getChild('customers');
        $this->assertNotNull($group);
        $group->addChild('customers')->setLabelAttribute('icon', 'tabler:users');

        $this->assertSame('tabler:user', $group->getLabelAttribute('icon'));
        $this->assertSame('tabler:users', $group->getChild('customers')?->getLabelAttribute('icon'));
    }

    public function testAMissingGroupIsIgnored(): void
    {
        $menu = $this->menuWith(['catalog']);

        $this->assertSame(['catalog'], array_keys($menu->getChildren()));
        $this->assertSame('tabler:book', $menu->getChild('catalog')?->getLabelAttribute('icon'));
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function menuGroups(): \Iterator
    {
        yield 'tableau de bord' => ['dashboard', 'tabler:dashboard'];
        yield 'catalogue' => ['catalog', 'tabler:book'];
        yield 'ventes' => ['sales', 'tabler:shopping-bag'];
        yield 'client' => ['customers', 'tabler:user'];
        yield 'marketing' => ['marketing', 'tabler:percentage'];
        yield 'configuration' => ['configuration', 'tabler:adjustments'];
        yield 'support officiel' => ['official_support', 'tabler:info-circle'];
        yield 'administration' => ['sylius.ui.administration', 'tabler:lock'];
        yield 'contenu' => ['yack_content', 'tabler:list'];
    }

    /**
     * @param list<string> $names
     */
    private function menuWith(array $names): ItemInterface
    {
        $factory = new MenuFactory();
        $menu = $factory->createItem('root');

        foreach ($names as $name) {
            $menu->addChild($name)->setLabelAttribute('icon', 'tabler:circle');
        }

        (new AdminMenuIconListener())(new MenuBuilderEvent($factory, $menu));

        return $menu;
    }
}
