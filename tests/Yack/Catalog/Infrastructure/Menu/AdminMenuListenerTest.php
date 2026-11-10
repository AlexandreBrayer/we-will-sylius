<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Menu;

use App\Yack\Catalog\Infrastructure\Menu\AdminMenuListener;
use Knp\Menu\Integration\Symfony\RoutingExtension;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(AdminMenuListener::class)]
final class AdminMenuListenerTest extends TestCase
{
    public function testTheCatalogMenuLeadsToTheSynchronisations(): void
    {
        $menu = $this->menu(true);

        $sync = $menu->getChild('catalog')?->getChild('yack_catalog_sync');
        $this->assertNotNull($sync);
        $this->assertSame('Synchronisation PIM/ERP', $sync->getLabel());
        $this->assertSame('/admin/yack/synchronisation', $sync->getUri());
        $this->assertSame('tabler:refresh', $sync->getLabelAttribute('icon'));
    }

    public function testNothingWithoutCatalogMenu(): void
    {
        $this->assertSame([], array_keys($this->menu(false)->getChildren()));
    }

    private function menu(bool $withCatalog): ItemInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnMap([
            ['app_yack_admin_catalog_sync_index', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/yack/synchronisation'],
        ]);
        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urls));
        $menu = $factory->createItem('root');
        if ($withCatalog) {
            $menu->addChild('catalog');
        }

        (new AdminMenuListener())(new MenuBuilderEvent($factory, $menu));

        return $menu;
    }
}
