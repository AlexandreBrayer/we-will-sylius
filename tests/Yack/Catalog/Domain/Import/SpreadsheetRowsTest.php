<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\Import\SpreadsheetRows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SpreadsheetRows::class)]
final class SpreadsheetRowsTest extends TestCase
{
    #[DataProvider('provideDelimiters')]
    public function testTheSeparatorAsShownInThePreview(?string $delimiter, ?string $label): void
    {
        $rows = new SpreadsheetRows(['Code'], [2 => ['SRK25ZS-W']], 'UTF-8', $delimiter);

        $this->assertSame([$label, ['Code'], [2 => ['SRK25ZS-W']], 'UTF-8'], [$rows->delimiterLabel(), $rows->headers, $rows->rows, $rows->encoding]);
    }

    /** @return \Iterator<string, array{string|null, string|null}> */
    public static function provideDelimiters(): \Iterator
    {
        yield 'xlsx' => [null, null];
        yield 'point-virgule' => [';', 'point-virgule'];
        yield 'virgule' => [',', 'virgule'];
        yield 'tabulation' => ["\t", 'tabulation'];
        yield 'autre' => ['|', '|'];
    }
}
