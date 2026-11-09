<?php

declare(strict_types=1);

namespace App\Tests\Conception;

use PHPUnit\Framework\TestCase;

/**
 * Aucun tiret long ni demi-cadratin dans un texte affiché : front (boutique, builder), gabarits Twig (back-office,
 * e-mails), fixtures (pages CMS, comptes, commandes, catalogue, manuels), configuration (RIB de démonstration),
 * schémas SVG du SAV, chaînes du PHP (messages d'erreur, libellés). Deux-points, parenthèses, virgule ou point
 * à la place ; dans un fil d'Ariane, la barre de séparation. Les commentaires de code ne sont pas contrôlés,
 * ni les textes des manuels indexés (`data/yack/assistance/textes`, données du fabricant non affichées).
 *
 * @internal
 */
final class VisibleTextDashesTest extends TestCase
{
    public function testNoDashInADisplayedText(): void
    {
        $files = array_merge(
            Rules::files('front/src', '#\.(ts|js|svelte)$#'),
            Rules::files('templates', '#\.twig$#'),
            Rules::files('config/yack', '#\.yaml$#'),
            Rules::files('config/packages', '#\.yaml$#'),
            Rules::files('translations', '#\.yaml$#'),
            Rules::files('data/yack', '#^data/yack/(catalog|api_feed_update|assistance/manuels)\.json$#'),
            Rules::files('public/yack', '#\.svg$#'),
            Rules::files('assets/shop/styles', '#\.scss$#'),
            Rules::files('assets/admin/styles', '#\.scss$#'),
            Rules::files('src', '#\.php$#'),
        );
        $this->assertGreaterThan(500, \count($files), 'Les dossiers contrôlés ont changé de place : mettre la liste à jour.');

        $found = [];
        foreach ($files as $file) {
            foreach (Rules::visibleDashLines((string) file_get_contents(Rules::root().'/'.$file), pathinfo($file, \PATHINFO_EXTENSION)) as $line) {
                $found[] = $file.':'.$line;
            }
        }

        $this->assertSame([], $found, 'Remplacer le tiret long ou le demi-cadratin par deux-points, parenthèses, virgule ou point (fil d\'Ariane : la barre).');
    }

    public function testTheRuleBites(): void
    {
        // Tirets écrits par leur point de code : ce fichier n'en contient aucun.
        $long = "\u{2014}";
        $half = "\u{2013}";

        $this->assertSame([3], Rules::visibleDashLines("<?php\n// Tiret {$long} permis en commentaire\n\$t = 'SAV {$long} n°';\n/** Doc {$half} permise */", 'php'));
        $this->assertSame([2, 5], Rules::visibleDashLines("// commentaire {$long} permis\n<p>Texte {$long} interdit</p>\n<!-- HTML {$long} permis\n -->\n{ label: `SAV {$half} \${key}` } /* bloc {$long} permis */", 'svelte'));
        $this->assertSame([], Rules::visibleDashLines("<a href=\"https://exemple.test\">Lien</a> // fin {$long} permis", 'svelte'));
        $this->assertSame([2], Rules::visibleDashLines("{# Gabarit {$long} permis #}\n<td>{{ v ?: '{$long}' }}</td>", 'twig'));
        $this->assertSame([2], Rules::visibleDashLines("# Fixtures {$long} permis\nname: 'PRO {$long} installateurs' # suite {$long} permise\ncolor: '#a33c87'", 'yaml'));
        $this->assertSame([2], Rules::visibleDashLines("<!-- schéma {$long} permis -->\n<text>Kaze {$half} vue éclatée</text>", 'svg'));
    }
}
