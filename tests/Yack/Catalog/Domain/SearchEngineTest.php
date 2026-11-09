<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\SearchEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchEngine::class)]
final class SearchEngineTest extends TestCase
{
    #[DataProvider('provideConfig')]
    public function testFromConfig(SearchEngine $expected, string $value): void
    {
        $this->assertSame($expected, SearchEngine::fromConfig($value));
    }

    /**
     * @return \Iterator<string, array{SearchEngine, string}>
     */
    public static function provideConfig(): \Iterator
    {
        yield 'meilisearch' => [SearchEngine::Meilisearch, 'meilisearch'];
        yield 'any case, spaces' => [SearchEngine::Meilisearch, ' MeiliSearch '];
        yield 'database' => [SearchEngine::Database, 'database'];
        yield 'empty' => [SearchEngine::Database, ''];
        yield 'unknown' => [SearchEngine::Database, 'elasticsearch'];
    }
}
