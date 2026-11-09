<?php

declare(strict_types=1);

namespace App\Tests\Conception;

use PHPUnit\Framework\TestCase;

/**
 * Le rapport des écarts de charte n'a pas d'entrée dans le back-office (ni menu, ni raccourci du tableau de bord, ni menu
 * Nouveautés de la barre de démo) : il s'ouvre par son adresse. Seuls ses propres écrans et la page 403 qui l'explique
 * pointent vers lui.
 *
 * @internal
 */
final class StyleDeviationReportEntryPointsTest extends TestCase
{
    private const ALLOWED = [
        'templates/admin/yack/content/style_deviations.html.twig',
        'templates/admin/yack/content/style_deviations_replace.html.twig',
        'templates/admin/yack/errors/403.html.twig',
    ];

    public function testNoOtherTemplateLinksToTheReport(): void
    {
        $linking = [];
        foreach (Rules::files('templates', '/\.twig$/') as $template) {
            $source = (string) file_get_contents(Rules::root().'/'.$template);
            if (str_contains($source, 'app_yack_admin_style_deviation_index') || str_contains($source, 'ecarts-de-charte')) {
                $linking[] = $template;
            }
        }

        $this->assertSame(self::ALLOWED, $linking);
    }
}
