<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BlockPath;
use App\Yack\Content\Domain\BlockType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlockPath::class)]
final class BlockPathTest extends TestCase
{
    public function testABlockOfThePageKeepsItsHistoricalLabel(): void
    {
        $this->assertSame('Bloc n°3', BlockPath::root(3)->label());
        $this->assertSame('Bloc n°3', BlockPath::root(3, BlockType::Faq)->label());
        $this->assertSame('Bloc n°3 (FAQ)', BlockPath::root(3, BlockType::Faq)->named(BlockType::Faq));
        $this->assertSame(3, BlockPath::root(3)->root);
    }

    public function testAContainerIsNamedByItsPath(): void
    {
        $this->assertSame('Section 2', BlockPath::root(2, BlockType::Section)->named(BlockType::Section));
        $this->assertSame('Colonnes 4', BlockPath::root(4, BlockType::Columns)->label());
    }

    public function testANestedBlockNamesEveryLevelAndKeepsItsBlockOfThePage(): void
    {
        $path = BlockPath::root(2, BlockType::Section)->child(3, BlockType::Columns)->column(2)->child(1, BlockType::Support);

        $this->assertSame('Section 2 › colonnes 3 › colonne 2 › bloc 1', $path->label());
        $this->assertSame("Section 2 › colonnes 3 › colonne 2 › bloc 1 (Cartes d'accompagnement)", $path->named(BlockType::Support));
        $this->assertSame(2, $path->root);
        $this->assertSame('Section 1 › section 2', BlockPath::root(1, BlockType::Section)->child(2, BlockType::Section)->label());
        $this->assertSame('Colonnes 1 › colonne 1 › bloc 5', BlockPath::root(1, BlockType::Columns)->column(1)->child(5)->label());
    }
}
