<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Infrastructure\Api;

use App\Yack\Documentation\Application\ProductDocumentation;
use App\Yack\Documentation\Infrastructure\Api\DocumentationCoverageView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocumentationCoverageView::class)]
final class DocumentationCoverageViewTest extends TestCase
{
    public function testFromDocumentation(): void
    {
        $view = DocumentationCoverageView::fromDocumentation(new ProductDocumentation('YK-PAE-M08', ['Notice Evolys 8 kW (FICTIF)'], 'Longueur ?'));

        $this->assertSame('YK-PAE-M08', $view->productCode);
        $this->assertSame(['Notice Evolys 8 kW (FICTIF)'], $view->notices);
        $this->assertSame('Longueur ?', $view->exampleQuestion);
    }
}
