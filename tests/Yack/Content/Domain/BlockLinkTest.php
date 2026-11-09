<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BlockLink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlockLink::class)]
final class BlockLinkTest extends TestCase
{
    #[DataProvider('provideAllowedLinks')]
    public function testTheTargetsOfTheShopAreAllowed(string $link): void
    {
        $this->assertTrue(BlockLink::isAllowed($link));
    }

    /**
     * Cases shared with the builder (front/test/link.test.ts replays them on isAllowedLink()), then the ones
     * only PHP can hold.
     *
     * @return \Iterator<string, array{string}>
     */
    public static function provideAllowedLinks(): \Iterator
    {
        foreach (self::sharedCases()['allowed'] as $name => $link) {
            yield $name => [$link];
        }
        yield 'longest link' => ['/' . str_repeat('a', BlockLink::MAX_LENGTH - 1)];
    }

    #[DataProvider('provideRefusedLinks')]
    public function testAnythingElseIsRefused(string $link): void
    {
        $this->assertFalse(BlockLink::isAllowed($link));
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function provideRefusedLinks(): \Iterator
    {
        foreach (self::sharedCases()['refused'] as $name => $link) {
            yield $name => [$link];
        }
        yield 'too long' => ['/' . str_repeat('a', BlockLink::MAX_LENGTH)];
        yield 'invalid utf-8' => ["/sav\xff"];
    }

    public function testTheLinksOfLabelAndLinkLinesAreReadAsTheShopReadsThem(): void
    {
        $this->assertSame(
            ['/produits', 'page:nos-services', '', '/a | b', 'javascript:x', '', "\u{200B}/sav", "/x\ry"],
            BlockLink::inLines("Catalogue | /produits\r\nServices |page:nos-services   \nSans lien\nBarre | /a | b\n | javascript:x\n\nInsécable | \u{00A0}\u{FEFF}\u{200B}/sav\u{2028}\nRetour | /x\ry"),
        );
        $this->assertSame([''], BlockLink::inLines(''));
    }

    /**
     * @return array{allowed: array<string, string>, refused: array<string, string>}
     */
    private static function sharedCases(): array
    {
        /** @var array{allowed: array<string, string>, refused: array<string, string>} $cases */
        $cases = json_decode((string) file_get_contents(__DIR__ . '/block-links.json'), true, 512, \JSON_THROW_ON_ERROR);

        return $cases;
    }

    public function testTheQuotedLinkIsShortAndWithoutControlCharacters(): void
    {
        $this->assertSame('javascript:alert(1)', BlockLink::quote('javascript:alert(1)'));
        $this->assertSame('?java?script:', BlockLink::quote("\x01java\u{202E}script:"));
        $this->assertSame(str_repeat('a', 79) . '…', BlockLink::quote(str_repeat('a', 200)));
        $this->assertTrue(mb_check_encoding(BlockLink::quote("/sav\xff"), 'UTF-8'));
    }
}
