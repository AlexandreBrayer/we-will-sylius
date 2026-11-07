<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Infrastructure\Source\SourceRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceRecord::class)]
final class SourceRecordTest extends TestCase
{
    public function testFromSomethingElseThanAnObjectIsEmpty(): void
    {
        $this->assertNull(SourceRecord::from('YK-PAE-M06')->optionalString('sku'));
        $this->assertSame('YK-PAE-M06', SourceRecord::from(['sku' => ' YK-PAE-M06 '])->optionalString('sku'));
    }

    public function testString(): void
    {
        $this->assertSame('Evolys', (new SourceRecord(['range' => 'Evolys ']))->string('range'));
    }

    #[DataProvider('provideMissingStrings')]
    public function testStringMissingOrEmpty(mixed $value): void
    {
        $this->expectException(InvalidSourceProduct::class);
        $this->expectExceptionMessage('Field "label" is missing or empty.');

        (new SourceRecord(['label' => $value]))->string('label');
    }

    /**
     * @return \Iterator<string, array{mixed}>
     */
    public static function provideMissingStrings(): \Iterator
    {
        yield 'null' => [null];
        yield 'blank' => ['  '];
        yield 'number' => [12];
    }

    #[DataProvider('provideInts')]
    public function testInt(int $expected, mixed $value): void
    {
        $this->assertSame($expected, (new SourceRecord(['stock' => $value]))->int('stock'));
    }

    /**
     * @return \Iterator<string, array{int, mixed}>
     */
    public static function provideInts(): \Iterator
    {
        yield 'int' => [14, 14];
        yield 'missing' => [0, null];
        yield 'numeric string' => [12, '12'];
        yield 'float truncated' => [12, 12.7];
        yield 'negative kept for the Domain' => [-3, -3];
    }

    public function testIntDefault(): void
    {
        $this->assertSame(5, (new SourceRecord([]))->int('stock', 5));
    }

    public function testIntRefusesText(): void
    {
        $this->expectException(InvalidSourceProduct::class);
        $this->expectExceptionMessage('Field "stock" is not a number (\'beaucoup\').');

        (new SourceRecord(['stock' => 'beaucoup']))->int('stock');
    }

    public function testNumberKeepsThePriceRead(): void
    {
        $this->assertSame(6490.0, (new SourceRecord(['amount' => '6490.00']))->number('amount'));
        $this->assertSame(649000, (int) round((float) (new SourceRecord(['amount' => '6490.00']))->number('amount') * 100));
        $this->assertSame(59.9, (new SourceRecord(['amount' => 59.9]))->number('amount'));
        $this->assertNull((new SourceRecord(['amount' => 'n/a']))->number('amount'));
        $this->assertNull((new SourceRecord([]))->number('amount'));
    }

    public function testRecord(): void
    {
        $price = (new SourceRecord(['price' => ['amount' => '59.00', 'currency' => 'EUR']]))->record('price');

        $this->assertInstanceOf(SourceRecord::class, $price);
        $this->assertSame('EUR', $price->optionalString('currency'));
        $this->assertNull((new SourceRecord(['price' => '59.00']))->record('price'));
    }

    public function testScalars(): void
    {
        $specs = (new SourceRecord(['specs' => ['power_kw' => 8.0, 'refrigerant' => 'R32', 'zones' => 2, 'wifi' => true, 'reversible' => false]]))->scalars('specs');

        $this->assertSame(['power_kw' => 8.0, 'refrigerant' => 'R32', 'zones' => 2, 'wifi' => '1', 'reversible' => ''], $specs);
        $this->assertSame([], (new SourceRecord([]))->scalars('specs'));
    }

    public function testScalarsRefusesANestedValue(): void
    {
        $this->expectException(InvalidSourceProduct::class);
        $this->expectExceptionMessage('Field "specs.power_kw" is not a scalar (array).');

        (new SourceRecord(['specs' => ['power_kw' => [8]]]))->scalars('specs');
    }

    public function testScalarsRefusesSomethingElseThanAnObject(): void
    {
        $this->expectException(InvalidSourceProduct::class);
        $this->expectExceptionMessage('Field "specs" is not an object.');

        (new SourceRecord(['specs' => 'R32']))->scalars('specs');
    }

    public function testText(): void
    {
        $this->assertSame('USD', (new SourceRecord(['currency' => 'USD']))->text('currency'));
        $this->assertSame('', (new SourceRecord([]))->text('currency'));
        $this->assertSame('array', (new SourceRecord(['currency' => ['EUR']]))->text('currency'));
    }
}
