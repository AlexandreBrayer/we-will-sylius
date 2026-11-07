<?php

declare(strict_types=1);

namespace App\Tests\Yack\Documentation\Domain;

use App\Yack\Documentation\Domain\Citation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Citation::class)]
final class CitationTest extends TestCase
{
    public function testSourceAndPageAreKept(): void
    {
        $citation = new Citation('Notice Evolys (FICTIF)', 12);

        $this->assertSame(['source' => 'Notice Evolys (FICTIF)', 'page' => 12], $citation->toArray());
        $this->assertTrue($citation->equals(new Citation('Notice Evolys (FICTIF)', 12)));
        $this->assertFalse($citation->equals(new Citation('Notice Evolys (FICTIF)', 13)));
        $this->assertFalse($citation->key() === (new Citation('Notice Kaze (FICTIF)', 12))->key());
    }

    #[DataProvider('invalidCitations')]
    public function testInvalidCitationIsRefused(string $source, int $page): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Citation($source, $page);
    }

    /** @return \Iterator<string, array{string, int}> */
    public static function invalidCitations(): \Iterator
    {
        yield 'source vide' => ['  ', 3];
        yield 'page zéro' => ['Notice', 0];
        yield 'page négative' => ['Notice', -1];
    }
}
