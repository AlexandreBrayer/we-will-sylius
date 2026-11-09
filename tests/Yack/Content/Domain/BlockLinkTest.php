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
     * @return \Iterator<string, array{string}>
     */
    public static function provideAllowedLinks(): \Iterator
    {
        yield 'no link' => [''];
        yield 'cms page' => ['page:nos-services'];
        yield 'home' => ['/'];
        yield 'path' => ['/sav'];
        yield 'path with a query' => ['/produits?marque=Yack'];
        yield 'path with a fragment' => ['/nos-services#faq'];
        yield 'https' => ['https://www.yack.fr/catalogue?x=1#a'];
        yield 'http' => ['http://example.org'];
        yield 'scheme in upper case' => ['HTTPS://EXAMPLE.ORG'];
        yield 'port' => ['https://example.org:8443/x'];
        yield 'ipv6' => ['https://[::1]:8080/x'];
        yield 'accented host' => ['https://yäck.fr/é'];
        yield 'mailto' => ['mailto:contact@example.org'];
        yield 'tel' => ['tel:+33596000000'];
        yield 'tel with spaces' => ['tel:+596 596 00 00 00'];
        yield 'tel with dots, hyphens and parentheses' => ['tel:(0596) 00.00-00'];
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
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript in mixed case' => ['JaVaScRiPt:alert(1)'];
        yield 'leading space' => [' javascript:alert(1)'];
        yield 'leading tab' => ["\tjavascript:alert(1)"];
        yield 'leading control character' => ["\x01javascript:alert(1)"];
        yield 'leading no-break space' => ["\u{00A0}/sav"];
        yield 'leading zero width space' => ["\u{200B}/sav"];
        yield 'right-to-left override' => ["/\u{202E}vas"];
        yield 'newline inside the scheme' => ["java\nscript:alert(1)"];
        yield 'trailing space' => ['/sav '];
        yield 'trailing newline' => ["/sav\n"];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'protocol relative' => ['//evil.example'];
        yield 'protocol relative with a backslash' => ['/\evil.example'];
        yield 'https without host' => ['https:///x'];
        yield 'https with a backslash host' => ['https://\x'];
        yield 'https without slashes' => ['https:evil.example'];
        yield 'https alone' => ['https://'];
        yield 'https with an empty host and a user' => ['https://a:b@'];
        yield 'https with a port that is not a number' => ['https://example.org:abc'];
        yield 'https with a space in the host' => ['https://exa mple.org'];
        yield 'numeric character reference' => ['&#106;avascript:alert(1)'];
        yield 'hexadecimal character reference in a path' => ['/x&#x2F;evil'];
        yield 'named character reference' => ['javascript&colon;alert(1)'];
        yield 'named character reference in a path' => ['/a&colon;b'];
        yield 'page in upper case' => ['PAGE:nos-services'];
        yield 'page with a path' => ['page:../x'];
        yield 'page slug in upper case' => ['page:Nos-services'];
        yield 'page without slug' => ['page:'];
        yield 'page with html' => ['page:"><img src=x onerror=alert(1)>'];
        yield 'html' => ['"><script>alert(1)</script>'];
        yield 'host without scheme' => ['www.yack.fr'];
        yield 'word' => ['sav'];
        yield 'mailto alone' => ['mailto:'];
        yield 'mailto with a space' => ['mailto:a b@example.org'];
        yield 'tel alone' => ['tel:'];
        yield 'tel without digit' => ['tel:+'];
        yield 'tel with letters' => ['tel:abc'];
        yield 'tel with a tab' => ["tel:+596\t596"];
        yield 'tel with a no-break space' => ["tel:+596\u{00A0}596"];
        yield 'tel with a semicolon' => ['tel:0596;x'];
        yield 'too long' => ['/' . str_repeat('a', BlockLink::MAX_LENGTH)];
        yield 'invalid utf-8' => ["/sav\xff"];
    }

    public function testTheQuotedLinkIsShortAndWithoutControlCharacters(): void
    {
        $this->assertSame('javascript:alert(1)', BlockLink::quote('javascript:alert(1)'));
        $this->assertSame('?java?script:', BlockLink::quote("\x01java\u{202E}script:"));
        $this->assertSame(str_repeat('a', 79) . '…', BlockLink::quote(str_repeat('a', 200)));
        $this->assertTrue(mb_check_encoding(BlockLink::quote("/sav\xff"), 'UTF-8'));
    }
}
