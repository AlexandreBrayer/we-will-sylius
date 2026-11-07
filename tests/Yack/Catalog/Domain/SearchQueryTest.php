<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SearchQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SearchQuery::class)]
final class SearchQueryTest extends TestCase
{
    public function testTheTextIsTrimmedOnSingleSpaces(): void
    {
        $query = new SearchQuery("  clim \t  murale\n ");

        $this->assertSame('clim murale', $query->text);
        $this->assertFalse($query->isBlank());
        $this->assertSame(SearchQuery::DEFAULT_PER_PAGE, $query->perPage);
        $this->assertSame(1, $query->page);
        $this->assertNull($query->kind);
    }

    public function testALongTextIsCut(): void
    {
        $query = new SearchQuery(str_repeat('é', 150));

        $this->assertSame(SearchQuery::MAX_LENGTH, mb_strlen($query->text));
    }

    public function testABlankText(): void
    {
        $this->assertTrue((new SearchQuery(" \n "))->isBlank());
        $this->assertSame([], (new SearchQuery(' , ; '))->terms());
    }

    #[DataProvider('providePaging')]
    public function testPagingIsBroughtBackInRange(int $perPage, int $page, int $expectedPerPage, int $expectedPage, int $expectedOffset): void
    {
        $query = new SearchQuery('pac', $perPage, $page);

        $this->assertSame($expectedPerPage, $query->perPage);
        $this->assertSame($expectedPage, $query->page);
        $this->assertSame($expectedOffset, $query->offset());
    }

    /**
     * @return \Iterator<string, array{int, int, int, int, int}>
     */
    public static function providePaging(): \Iterator
    {
        yield 'first page' => [9, 1, 9, 1, 0];
        yield 'third page' => [18, 3, 18, 3, 36];
        yield 'page size too small' => [0, 2, 1, 2, 1];
        yield 'page size too large' => [500, 1, SearchQuery::MAX_PER_PAGE, 1, 0];
        yield 'page before the first' => [9, -4, 9, 1, 0];
    }

    public function testTermsAreDistinctLowerCaseWords(): void
    {
        $query = new SearchQuery('Pompe à CHALEUR, pompe ; air/eau');

        $this->assertSame(['pompe', 'à', 'chaleur', 'air/eau'], $query->terms());
    }

    public function testOnlyTheFirstTermsAreKept(): void
    {
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f'], (new SearchQuery('a b c d e f g h'))->terms());
    }

    #[DataProvider('provideInput')]
    public function testFromInput(?string $text, ?string $limit, ?string $page, ?string $type, string $expectedText, int $expectedPerPage, int $expectedPage, ?ProductKind $expectedKind): void
    {
        $query = SearchQuery::fromInput($text, $limit, $page, $type);

        $this->assertSame($expectedText, $query->text);
        $this->assertSame($expectedPerPage, $query->perPage);
        $this->assertSame($expectedPage, $query->page);
        $this->assertSame($expectedKind, $query->kind);
    }

    /**
     * @return \Iterator<string, array{string|null, string|null, string|null, string|null, string, int, int, ProductKind|null}>
     */
    public static function provideInput(): \Iterator
    {
        yield 'nothing given' => [null, null, null, null, '', SearchQuery::DEFAULT_PER_PAGE, 1, null];
        yield 'everything given' => ['SRK25', '18', '2', 'part', 'SRK25', 18, 2, ProductKind::SparePart];
        yield 'finished products' => ['clim', ' 9 ', '1', 'product', 'clim', 9, 1, ProductKind::Finished];
        yield 'not numbers' => ['clim', 'dix', '2.5', '', 'clim', SearchQuery::DEFAULT_PER_PAGE, 1, null];
        yield 'negative number' => ['clim', '-3', '-1', null, 'clim', SearchQuery::DEFAULT_PER_PAGE, 1, null];
        yield 'unknown type' => ['clim', null, null, 'accessoire', 'clim', SearchQuery::DEFAULT_PER_PAGE, 1, null];
    }
}
