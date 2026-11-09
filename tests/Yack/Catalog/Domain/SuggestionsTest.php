<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SearchEngine;
use App\Yack\Catalog\Domain\Suggestions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Suggestions::class)]
final class SuggestionsTest extends TestCase
{
    public function testNone(): void
    {
        $suggestions = Suggestions::none(SearchEngine::Meilisearch);

        $this->assertSame([], $suggestions->products);
        $this->assertSame([], $suggestions->categories);
        $this->assertSame(SearchEngine::Meilisearch, $suggestions->engine);
    }
}
