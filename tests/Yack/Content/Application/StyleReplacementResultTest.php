<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Yack\Content\Application\StyleReplacementResult;
use App\Yack\Content\Domain\CmsRevisionTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StyleReplacementResult::class)]
final class StyleReplacementResultTest extends TestCase
{
    public function testItCountsBlocksPagesAndTemplates(): void
    {
        $result = new StyleReplacementResult([
            ['target' => CmsRevisionTarget::Page, 'ref' => 'demo', 'label' => 'Démo', 'blocks' => [1, 3]],
            ['target' => CmsRevisionTarget::Page, 'ref' => 'offre', 'label' => 'Offre', 'blocks' => [2]],
            ['target' => CmsRevisionTarget::Template, 'ref' => '4', 'label' => 'Standard', 'blocks' => [5]],
        ]);

        $this->assertSame(4, $result->blocks());
        $this->assertSame(2, $result->pages());
        $this->assertSame(1, $result->templates());
        $this->assertFalse($result->isEmpty());
    }

    public function testAnEmptyResultTouchesNothing(): void
    {
        $result = new StyleReplacementResult([]);

        $this->assertTrue($result->isEmpty());
        $this->assertSame([0, 0, 0], [$result->blocks(), $result->pages(), $result->templates()]);
    }
}
