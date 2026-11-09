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
            ['target' => CmsRevisionTarget::Page, 'ref' => 'demo', 'label' => 'Démo', 'blocks' => ['Bloc n°1', 'Bloc n°3']],
            ['target' => CmsRevisionTarget::Page, 'ref' => 'offre', 'label' => 'Offre', 'blocks' => ['Bloc n°2']],
            ['target' => CmsRevisionTarget::Template, 'ref' => '4', 'label' => 'Standard', 'blocks' => ['Bloc n°5']],
        ]);

        $this->assertSame(4, $result->blocks());
        $this->assertSame(2, $result->pages());
        $this->assertSame(1, $result->templates());
        $this->assertFalse($result->isEmpty());
    }

    public function testItCountsTheContentsLeftOutAsLockedApart(): void
    {
        $since = new \DateTimeImmutable('2026-11-09 14:05:00');
        $result = new StyleReplacementResult([
            ['target' => CmsRevisionTarget::Page, 'ref' => 'offre', 'label' => 'Offre', 'blocks' => ['Bloc n°2']],
        ], [
            ['target' => CmsRevisionTarget::Page, 'ref' => 'demo', 'label' => 'Démo', 'blocks' => ['Bloc n°1', 'Bloc n°3'], 'holder' => 'Paul Durand', 'since' => $since],
            ['target' => CmsRevisionTarget::Template, 'ref' => '4', 'label' => 'Standard', 'blocks' => ['Bloc n°5'], 'holder' => 'Paul Durand', 'since' => $since],
        ]);

        $this->assertSame([1, 1, 0], [$result->blocks(), $result->pages(), $result->templates()]);
        $this->assertSame([3, 1, 1], [$result->skippedBlocks(), $result->skippedPages(), $result->skippedTemplates()]);
        $this->assertSame(4, $result->matchedBlocks());
        $this->assertFalse($result->isEmpty());
    }

    public function testEverythingLockedIsNotEmpty(): void
    {
        $result = new StyleReplacementResult([], [
            ['target' => CmsRevisionTarget::Page, 'ref' => 'demo', 'label' => 'Démo', 'blocks' => ['Bloc n°1'], 'holder' => 'Paul Durand', 'since' => new \DateTimeImmutable()],
        ]);

        $this->assertSame([0, 1], [$result->blocks(), $result->matchedBlocks()]);
        $this->assertFalse($result->isEmpty(), 'a block holds the value');
    }

    public function testAnEmptyResultTouchesNothing(): void
    {
        $result = new StyleReplacementResult([]);

        $this->assertTrue($result->isEmpty());
        $this->assertSame([0, 0, 0], [$result->blocks(), $result->pages(), $result->templates()]);
        $this->assertSame([0, 0, 0, 0], [$result->skippedBlocks(), $result->skippedPages(), $result->skippedTemplates(), $result->matchedBlocks()]);
    }
}
