<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\CmsSectionName;
use App\Yack\Content\Domain\CmsSectionRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsSectionName::class)]
final class CmsSectionNameTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function names(): \Iterator
    {
        yield 'kept as is' => ['Bandeau promo', 'Bandeau promo'];
        yield 'trimmed' => ["  Bandeau promo \n", 'Bandeau promo'];
        yield 'line breaks and tabs' => ["Deux\nlignes\tet\r\nplus", 'Deux lignes et plus'];
        yield 'runs of spaces' => ['Appel   à  l\'action', 'Appel à l\'action'];
        yield 'no-break and zero-width spaces' => ["Offre\u{00A0}\u{200B}PAC", 'Offre PAC'];
        yield 'line separator, control character' => ["Offre\u{2028}PAC\x07", 'Offre PAC'];
        yield 'invalid UTF-8 replaced' => ["Offre \xFF", 'Offre ?'];
        yield 'exactly 120 characters' => [str_repeat('é', 120), str_repeat('é', 120)];
        yield '120 characters once cleaned' => [' ' . str_repeat('é', 120) . "\n", str_repeat('é', 120)];
    }

    #[DataProvider('names')]
    public function testANameIsOneCleanLine(string $sent, string $kept): void
    {
        $this->assertSame($kept, CmsSectionName::clean($sent));
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function refused(): \Iterator
    {
        yield 'empty' => ['', 'Donnez un nom à la section.'];
        yield 'spaces and line breaks only' => [" \n\t\u{00A0}", 'Donnez un nom à la section.'];
        yield '121 characters' => [str_repeat('é', 121), 'Le nom de la section fait au plus 120 caractères.'];
    }

    #[DataProvider('refused')]
    public function testAnEmptyOrTooLongNameIsRefusedOnTheName(string $sent, string $message): void
    {
        try {
            CmsSectionName::clean($sent);
            $this->fail('The name must be refused.');
        } catch (CmsSectionRefused $refused) {
            $this->assertSame(['name', $message], [$refused->field, $refused->getMessage()]);
        }
    }
}
