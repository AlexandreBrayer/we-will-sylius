<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Domain\InvalidPageBlocks;
use App\Yack\Content\Domain\PageBlocks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PageBlocks::class)]
final class PageBlocksTest extends TestCase
{
    public function testNormalizeFillsMissingPropsWithTheirDefault(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'cta', 'props' => ['title' => 'Vous êtes installateur ?']]]);

        $this->assertSame('cta', $blocks[0]['type']);
        $this->assertSame('Vous êtes installateur ?', $blocks[0]['props']['title']);
        $this->assertSame('magenta', $blocks[0]['props']['theme']);
        $this->assertSame('tous', $blocks[0]['props']['audience']);
    }

    public function testOnlyFreeBlocksAcceptAnAudience(): void
    {
        foreach (BlockType::cases() as $type) {
            $expected = null === $type->scope() ? BlockType::AUDIENCES : null;
            $this->assertSame($expected, $type->props()['audience'] ?? null, $type->label());
        }
    }

    public function testKeyFiguresBlockExposesFourSlots(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'chiffres-cles', 'props' => ['value1' => '48 h', 'label1' => 'Livraison']]]);

        $this->assertSame('48 h', $blocks[0]['props']['value1']);
        $this->assertSame('', $blocks[0]['props']['value4']);
        $this->assertSame('clair', $blocks[0]['props']['theme']);
    }

    public function testQuickAccessBlockOnlyAcceptsKnownPictos(): void
    {
        $blocks = PageBlocks::normalize([['type' => 'acces-rapides', 'props' => ['icon1' => 'panier', 'label1' => 'Commander']]]);

        $this->assertSame('panier', $blocks[0]['props']['icon1']);
        $this->assertSame('livraison', $blocks[0]['props']['icon2']);
    }

    #[DataProvider('provideInvalidBlocks')]
    public function testNormalizeRefusesContentOutsideTheCharte(mixed $blocks, string $expectedMessage): void
    {
        $this->expectException(InvalidPageBlocks::class);
        $this->expectExceptionMessage($expectedMessage);

        PageBlocks::normalize($blocks);
    }

    /**
     * @return \Iterator<string, array{mixed, string}>
     */
    public static function provideInvalidBlocks(): \Iterator
    {
        yield 'not a list' => [['hero' => []], 'Le contenu doit être une liste de blocs.'];
        yield 'unknown block' => [[['type' => 'html', 'props' => []]], 'type « html » inconnu'];
        yield 'unknown prop' => [[['type' => 'hero', 'props' => ['style' => 'color:red']]], 'propriété(s) non autorisée(s) : style'];
        yield 'free colour' => [[['type' => 'hero', 'props' => ['theme' => '#ff0000']]], '« #ff0000 » n\'est pas une valeur autorisée pour « theme »'];
        yield 'unknown audience' => [[['type' => 'faq', 'props' => ['audience' => 'admin']]], '« admin » n\'est pas une valeur autorisée pour « audience »'];
        yield 'unknown picto' => [[['type' => 'acces-rapides', 'props' => ['icon1' => 'fusee']]], '« fusee » n\'est pas une valeur autorisée pour « icon1 »'];
        yield 'too many blocks' => [array_fill(0, PageBlocks::MAX_BLOCKS + 1, ['type' => 'faq', 'props' => []]), 'Une page contient au plus 30 blocs.'];
    }
}
