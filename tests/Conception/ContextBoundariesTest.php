<?php

declare(strict_types=1);

namespace App\Tests\Conception;

use PHPUnit\Framework\TestCase;

/**
 * ADR 008 : un contexte ne lit jamais l'Infrastructure d'un autre contexte (Common excepté), et un Domain ne
 * dépend ni de l'Application ni de l'Infrastructure d'un autre contexte. Pour lire chez un voisin : une
 * interface exposée dans son Application.
 *
 * @internal
 */
final class ContextBoundariesTest extends TestCase
{
    public function testNoContextImportsTheInfrastructureOfAnother(): void
    {
        $violations = [];
        foreach (Rules::files('src/Yack', '#^src/Yack/\w+/(Domain|Application|Infrastructure)/.+\.php$#') as $file) {
            [, , $context, $layer] = explode('/', $file);
            foreach (Rules::forbiddenImports($context, $layer, (string) file_get_contents(Rules::root().'/'.$file)) as $use) {
                $violations[] = $file.' : '.$use;
            }
        }

        $this->assertSame([], $violations, "Passer par une interface de l'Application du contexte lu (ADR 008).");
    }

    public function testTheRuleBites(): void
    {
        $source = "<?php\nuse App\\Yack\\Catalog\\Infrastructure\\Sylius\\VariantStock;\nuse App\\Yack\\Catalog\\Application\\VariantStockReaderInterface;\nuse App\\Yack\\Common\\Infrastructure\\Image\\ExifStripper;\n";

        $this->assertSame(['use App\\Yack\\Catalog\\Infrastructure\\Sylius\\VariantStock;'], Rules::forbiddenImports('AfterSales', 'Application', $source));
        $this->assertCount(2, Rules::forbiddenImports('AfterSales', 'Domain', $source));
        $this->assertSame([], Rules::forbiddenImports('Catalog', 'Infrastructure', $source));
    }
}
