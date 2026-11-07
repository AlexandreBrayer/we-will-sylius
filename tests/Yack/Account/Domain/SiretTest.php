<?php

declare(strict_types=1);

namespace App\Tests\Yack\Account\Domain;

use App\Yack\Account\Domain\Siret;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SiretTest extends TestCase
{
    #[DataProvider('provideSirets')]
    public function testIsValid(bool $expected, string $siret): void
    {
        $this->assertSame($expected, Siret::isValid($siret));
    }

    /**
     * @return \Iterator<string, array{bool, string}>
     */
    public static function provideSirets(): \Iterator
    {
        yield 'valid' => [true, '73282932000074'];
        yield 'valid with spaces' => [true, '732 829 320 00074'];
        yield 'other valid' => [true, '81234567800013'];
        yield 'wrong check digit' => [false, '73282932000075'];
        yield 'too short' => [false, '7328293200007'];
        yield 'letters' => [false, '7328293200007A'];
    }

    #[DataProvider('provideVatNumbers')]
    public function testIsValidFrenchVatNumber(bool $expected, string $vatNumber, ?string $siret): void
    {
        $this->assertSame($expected, Siret::isValidFrenchVatNumber($vatNumber, $siret));
    }

    /**
     * @return \Iterator<string, array{bool, string, ?string}>
     */
    public static function provideVatNumbers(): \Iterator
    {
        yield 'valid' => [true, 'FR44732829320', null];
        yield 'valid, matches the SIRET' => [true, 'fr 44 732829320', '73282932000074'];
        yield 'wrong key' => [false, 'FR45732829320', null];
        yield 'other company than the SIRET' => [false, 'FR44732829320', '81234567800013'];
        yield 'not French' => [false, 'BE0123456789', null];
    }
}
