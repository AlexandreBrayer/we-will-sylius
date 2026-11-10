<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain;

use App\Yack\Catalog\Domain\ResultPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ResultPage::class)]
final class ResultPageTest extends TestCase
{
    #[DataProvider('provideNumbers')]
    public function testThePageAskedForIsBroughtBackBetweenOneAndTenThousand(string|int $asked, int $page): void
    {
        $this->assertSame($page, ResultPage::number($asked));
    }

    /** @return \Iterator<string, array{string|int, int}> */
    public static function provideNumbers(): \Iterator
    {
        yield 'texte' => ['3', 3];
        yield 'entier' => [7, 7];
        yield 'zéro' => ['0', 1];
        yield 'négatif' => [-4, 1];
        yield 'pas un nombre' => ['deux', 1];
        yield 'trop grand' => ['99999', 10000];
    }

    public function testPagesAndOffset(): void
    {
        $page = new ResultPage(['a', 'b'], 3, 15, 31);

        $this->assertSame([3, 30, ['a', 'b']], [$page->pages(), $page->offset(), $page->items]);
        $this->assertSame(1, (new ResultPage([], 1, 15, 0))->pages());
        $this->assertSame(5, (new ResultPage([], 1, 0, 5))->pages());
    }

    /** @param list<int|null> $window */
    #[DataProvider('provideWindows')]
    public function testTheWindowKeepsTheEndsAndTwoPagesAroundTheCurrentOne(int $current, int $total, array $window): void
    {
        $this->assertSame($window, (new ResultPage([], $current, 10, $total))->window());
    }

    /** @return \Iterator<string, array{int, int, list<int|null>}> */
    public static function provideWindows(): \Iterator
    {
        yield 'une page' => [1, 0, [1]];
        yield 'peu de pages' => [2, 50, [1, 2, 3, 4, 5]];
        yield 'au début' => [1, 200, [1, 2, 3, null, 20]];
        yield 'au milieu' => [10, 200, [1, null, 8, 9, 10, 11, 12, null, 20]];
        yield 'à la fin' => [20, 200, [1, null, 18, 19, 20]];
        yield 'près du début' => [4, 100, [1, 2, 3, 4, 5, 6, null, 10]];
    }
}
