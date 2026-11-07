<?php

declare(strict_types=1);

namespace App\Tests\Conception;

use PHPUnit\Framework\TestCase;

/**
 * La doc et les commentaires disent le présent et la cible, pas l'histoire (le récit va dans Git, les MR et
 * les ADR) ; tout chemin du dépôt cité dans la doc existe. Les ADR, datés par nature, ne sont pas contrôlés.
 *
 * @internal
 */
final class DocumentationTest extends TestCase
{
    public function testPresentStateDocsAndCommentsTellNoStory(): void
    {
        $docs = array_merge(['AGENTS.md', 'README.md', 'front/README.md'], Rules::files('docs', '#^docs/[^/]+\.md$#'), Rules::files('src/Yack', '#README\.md$#'));
        $code = array_merge(
            Rules::files('src/Yack', '#\.php$#'),
            Rules::files('front/src', '#\.(ts|svelte)$#'),
            Rules::files('assets/shop/styles', '#\.scss$#'),
            Rules::files('config/yack', '#\.yaml$#'),
            Rules::files('config/packages', '#\.yaml$#'),
        );

        $found = [];
        foreach ([[$docs, false], [$code, true]] as [$files, $commentsOnly]) {
            foreach ($files as $file) {
                foreach (Rules::narrativeLines((string) file_get_contents(Rules::root().'/'.$file), $commentsOnly) as $line) {
                    $found[] = $file.':'.$line;
                }
            }
        }

        $this->assertSame([], $found, 'Écrire le pourquoi au présent (avec l\'ADR), sans lot, ticket ni date de livraison.');
    }

    public function testEveryRepositoryPathCitedInTheDocsExists(): void
    {
        $root = Rules::root();
        $exists = static fn (string $path): bool => file_exists($root.'/'.$path);
        $dead = [];
        foreach (array_merge(['AGENTS.md', 'README.md', 'front/README.md'], Rules::files('docs', '#\.md$#'), Rules::files('src/Yack', '#README\.md$#')) as $file) {
            foreach (Rules::deadPaths((string) file_get_contents($root.'/'.$file), \dirname($file), $exists) as $path) {
                $dead[] = $file.' -> '.$path;
            }
        }

        $this->assertSame([], $dead, 'Corriger ou retirer le chemin cité.');
    }

    public function testTheRulesBite(): void
    {
        $this->assertSame([2, 3], Rules::narrativeLines("Le stock est exposé.\nAjouté au lot 1.\nRésorbé par L0-01.\nPrévu au lot 2.", false));
        $this->assertSame([2], Rules::narrativeLines("\$x = 'L0-01';\n// Fil d'Ariane (L1-05) gris", true));
        $this->assertSame(['src/Absent/Nulle.php'], Rules::deadPaths('Voir `src/Absent/Nulle.php` et `docs/ok.md`.', 'docs', static fn (string $p): bool => 'docs/ok.md' === $p));
    }
}
