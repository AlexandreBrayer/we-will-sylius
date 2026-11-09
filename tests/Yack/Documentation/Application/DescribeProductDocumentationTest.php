<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Application;

use App\Yack\Documentation\Application\DescribeProductDocumentation;
use App\Yack\Documentation\Application\ProductDocumentation;
use App\Yack\Documentation\Domain\Notice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DescribeProductDocumentation::class)]
#[CoversClass(ProductDocumentation::class)]
final class DescribeProductDocumentationTest extends TestCase
{
    public function testNoticesAndFirstExampleOfTheProduct(): void
    {
        $describe = new DescribeProductDocumentation(new InMemoryDocumentationCorpus([
            new Notice('Notice A', 'A', ['YK-PAE-M08'], null, []),
            new Notice('Notice B', 'B', ['YK-PAE-M08', 'YK-PAE-M06'], 'Longueur de liaison ?', []),
            new Notice('Notice C', 'C', ['YK-PAE-M08'], 'Autre question ?', []),
            new Notice('Notice D', 'D', ['YK-ECS-T200'], 'Anode ?', []),
        ]));

        $documentation = $describe(' yk-pae-m08 ');

        $this->assertSame('YK-PAE-M08', $documentation->productCode);
        $this->assertSame(['Notice A', 'Notice B', 'Notice C'], $documentation->notices);
        $this->assertSame('Longueur de liaison ?', $documentation->exampleQuestion);
        $this->assertTrue($documentation->hasNotice());
    }

    public function testProductWithoutNotice(): void
    {
        $documentation = (new DescribeProductDocumentation(new InMemoryDocumentationCorpus([])))('YK-PAE-M06');

        $this->assertSame([], $documentation->notices);
        $this->assertNull($documentation->exampleQuestion);
        $this->assertFalse($documentation->hasNotice());
    }
}
