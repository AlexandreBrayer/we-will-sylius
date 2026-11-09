<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Menu;

use App\Yack\Content\Infrastructure\Menu\AdminMenuListener;
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
    public function testTheContentMenuListsTheBuilderThePagesAndTheTemplates(): void
    {
        $content = $this->menu()->getChild('yack_content');

        $this->assertNotNull($content);
        $this->assertSame('Contenu', $content->getLabel());
        $this->assertSame(['yack_cms_builder', 'yack_cms_pages', 'yack_cms_templates'], array_keys($content->getChildren()));
        $this->assertSame(
            ['/admin/yack/builder', '/admin/yack/contenu/pages', '/admin/yack/contenu/templates'],
            array_values(array_map(static fn (ItemInterface $item): ?string => $item->getUri(), $content->getChildren())),
        );
        $this->assertSame('_blank', $content->getChild('yack_cms_builder')?->getLinkAttribute('target'));
    }

    public function testTheStyleDeviationReportIsNotInTheMenu(): void
    {
        $uris = array_map(static fn (ItemInterface $item): ?string => $item->getUri(), $this->menu()->getChild('yack_content')?->getChildren() ?? []);

        $this->assertNotContains('/admin/yack/contenu/ecarts-de-charte', $uris);
        $this->assertNull($this->menu()->getChild('yack_content')?->getChild('yack_style_deviations'));
    }

    private function menu(): ItemInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnMap([
            ['app_yack_admin_builder', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/yack/builder'],
            ['app_yack_admin_cms_page_index', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/yack/contenu/pages'],
            ['app_yack_admin_cms_template_index', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/yack/contenu/templates'],
            ['app_yack_admin_style_deviation_index', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/yack/contenu/ecarts-de-charte'],
        ]);
        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urls));
        $menu = $factory->createItem('root');

        (new AdminMenuListener())(new MenuBuilderEvent($factory, $menu));

        return $menu;
    }
}
