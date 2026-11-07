<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Domain;

use App\Yack\AfterSales\Domain\SerialNumberFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SerialNumberFormat::class)]
final class SerialNumberFormatTest extends TestCase
{
    public function testNormalizeIsLenientOnCaseSpacesAndDashes(): void
    {
        $this->assertSame('YK19KAZ35S004567', SerialNumberFormat::normalize(' yk19-kaz35s 004.567_ '));
    }

    #[DataProvider('plausibleSerials')]
    public function testPlausibleSerials(string $read): void
    {
        $this->assertTrue(SerialNumberFormat::isPlausible($read));
    }

    #[DataProvider('implausibleSerials')]
    public function testImplausibleSerials(string $read): void
    {
        $this->assertFalse(SerialNumberFormat::isPlausible($read));
    }

    /** @return \Iterator<string, array{string}> */
    public static function plausibleSerials(): \Iterator
    {
        yield 'demo serial' => ['YK24HYD11S000321'];
        yield 'with spaces and dashes' => ['yk24-hyd11s 000321'];
        yield 'digits only' => ['123456'];
        yield 'longest' => [str_repeat('A1', 16)];
    }

    /** @return \Iterator<string, array{string}> */
    public static function implausibleSerials(): \Iterator
    {
        yield 'empty' => [''];
        yield 'too short' => ['YK12'];
        yield 'too long' => [str_repeat('A1', 16).'9'];
        yield 'a word, no digit' => ['ILLISIBLE'];
        yield 'other characters' => ['YK24/HYD11S'];
        yield 'accented' => ['YKÉ24HYD11S'];
    }
}
