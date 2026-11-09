<?php

declare(strict_types=1);

namespace App\Tests\Conception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Un administrateur connecté qui reçoit un 403 voit une page française dans le cadre du back-office, comme la 404 de
 * Sylius : l'ErrorTemplateFinder de Sylius cherche `@SyliusAdmin/errors/error403.html.twig` (surchargé dans
 * templates/bundles), sinon Symfony répond par sa page anglaise. Contenu rendu ici par Twig seul (sans noyau).
 *
 * @internal
 */
final class AdminAccessDeniedPageTest extends TestCase
{
    private const PATHS = [
        'app_yack_admin_style_deviation_index' => '/admin/yack/contenu/ecarts-de-charte',
        'sylius_admin_dashboard' => '/admin/',
        'app_yack_admin_builder' => '/admin/yack/builder',
    ];

    public function testTheSyliusAdminErrorPageForA403IsOverriddenAndHooked(): void
    {
        $page = (string) file_get_contents(Rules::root().'/templates/bundles/SyliusAdminBundle/errors/error403.html.twig');
        $this->assertStringContainsString("{% extends '@SyliusAdmin/shared/layout/base.html.twig' %}", $page);
        $this->assertStringContainsString("{% hook 'sylius_admin.errors.403' %}", $page);
        $this->assertStringContainsString('Accès refusé', $page);

        $hooks = Yaml::parseFile(Rules::root().'/config/packages/yack.yaml')['sylius_twig_hooks']['hooks'];
        $this->assertSame(
            ['sidebar', 'navbar', 'content'],
            array_keys($hooks['sylius_admin.errors.403'] ?? []),
            'Même cadre que sylius_admin.errors.404 (barre latérale, barre du haut, contenu).',
        );
        $this->assertSame('admin/yack/errors/403.html.twig', $hooks['sylius_admin.errors.403.content']['error']['template'] ?? null);
    }

    public function testTheReportSaysWhyAndOffersTheDashboardAndTheBuilder(): void
    {
        $html = $this->render('/admin/yack/contenu/ecarts-de-charte');

        $this->assertStringContainsString('<h1 class="h2">Accès refusé</h1>', $html);
        $this->assertStringContainsString('réservé aux administrateurs de niveau Libre', $html);
        $this->assertStringContainsString('href="/admin/"', $html);
        $this->assertStringContainsString('Retour au tableau de bord', $html);
        $this->assertStringContainsString('href="/admin/yack/builder"', $html);
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z]+ (Error|Forbidden|Oops)/', $html);
    }

    public function testAnyOtherPageGivesTheGeneralReasonAndTheDashboard(): void
    {
        $html = $this->render('/admin/users/');

        $this->assertStringContainsString("Votre compte n'a pas les droits nécessaires pour ouvrir cette page.", $html);
        $this->assertStringContainsString('href="/admin/"', $html);
        $this->assertStringNotContainsString('niveau Libre', $html);
        $this->assertStringNotContainsString('/admin/yack/builder', $html);
    }

    private function render(string $path): string
    {
        $twig = new Environment(new FilesystemLoader(Rules::root().'/templates'), ['strict_variables' => true, 'autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => self::PATHS[$route]));

        return $twig->render('admin/yack/errors/403.html.twig', ['app' => ['request' => Request::create($path)]]);
    }
}
