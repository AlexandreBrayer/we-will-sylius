<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\SearchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchResult::class)]
final class SearchResultTest extends TestCase
{
    public function testNone(): void
    {
        $result = SearchResult::none(SearchEngine::Database);

        $this->assertSame([], $result->codes);
        $this->assertSame(0, $result->total);
        $this->assertSame(SearchEngine::Database, $result->engine);
    }
}
