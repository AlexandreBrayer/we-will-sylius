<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Menu;

use App\Yack\Content\Infrastructure\Menu\AdminMenuListener;
use App\Yack\Content\Infrastructure\Security\StyleDeviationVoter;
use Knp\Menu\Integration\Symfony\RoutingExtension;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(AdminMenuListener::class)]
final class AdminMenuListenerTest extends TestCase
{
    public function testTheContentMenuShowsTheReportToTheAdministratorsAllowedToOpenIt(): void
    {
        $content = $this->menu(true)->getChild('yack_content');

        $this->assertNotNull($content);
        $this->assertSame('Contenu', $content->getLabel());
        $this->assertSame(['yack_cms_builder', 'yack_cms_pages', 'yack_cms_templates', 'yack_style_deviations'], array_keys($content->getChildren()));
        $report = $content->getChild('yack_style_deviations');
        $this->assertNotNull($report);
        $this->assertSame('Écarts de charte', $report->getLabel());
        $this->assertSame('/admin/yack/contenu/ecarts-de-charte', $report->getUri());
        $this->assertSame('tabler:palette', $report->getLabelAttribute('icon'));
    }

    public function testTheOtherAdministratorsDoNotSeeIt(): void
    {
        $content = $this->menu(false)->getChild('yack_content');

        $this->assertSame(['yack_cms_builder', 'yack_cms_pages', 'yack_cms_templates'], array_keys($content?->getChildren() ?? []));
    }

    private function menu(bool $granted): ItemInterface
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->expects($this->once())->method('isGranted')->with(StyleDeviationVoter::ATTRIBUTE)->willReturn($granted);
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

        (new AdminMenuListener($authorization))(new MenuBuilderEvent($factory, $menu));

        return $menu;
    }
}
