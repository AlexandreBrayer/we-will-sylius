<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Domain;

use App\Yack\Assistance\Domain\ModelReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ModelReference::class)]
final class ModelReferenceTest extends TestCase
{
    /** @return \Iterator<string, array{string}> */
    public static function writingsOfTheSameReference(): \Iterator
    {
        yield 'tiret demi-cadratin et espaces' => ['srk25zs – w'];
        yield 'espace avant le suffixe' => ['SRK25ZS W'];
        yield 'libellé en tête' => ['Model: SRK25ZS-W'];
        yield 'libellé français et point final' => ['Réf. SRK25ZS-W.'];
        yield 'tiret moins et espace insécable' => ["\u{00A0}srk25zs \u{2212} W "];
    }

    #[DataProvider('writingsOfTheSameReference')]
    public function testWritingsOfTheSameReferenceAreNormalizedAndSplit(string $input): void
    {
        $reference = ModelReference::fromInput($input);

        $this->assertSame('SRK25ZS-W', $reference?->canonical);
        $this->assertSame(trim($input), $reference->read);
        $this->assertTrue($reference->structured);
        $this->assertFalse($reference->corrected);
        $this->assertSame(['SRK', '25', 'ZS', 'W'], [$reference->prefix, $reference->capacity, $reference->series, $reference->suffix]);
    }

    public function testAReadingConfusionInTheCapacityIsCorrected(): void
    {
        $reference = ModelReference::fromInput('SRK2SZS-W');

        $this->assertSame('SRK25ZS-W', $reference?->canonical);
        $this->assertSame('SRK2SZS-W', $reference->read);
        $this->assertTrue($reference->corrected);
        $this->assertTrue($reference->structured);
        $this->assertFalse(ModelReference::fromInput('SRK25ZS-W')?->corrected);
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function confusions(): \Iterator
    {
        yield 'O lu pour 0 sur trois chiffres' => ['SRK1OOZR-W', 'SRK100ZR-W'];
        yield 'I lu pour 1' => ['FDT7IVH', 'FDT71VH'];
        yield 'B lu pour 8' => ['SRKB0ZR-W', 'SRK80ZR-W'];
        yield '5 lu pour S dans le préfixe' => ['5RK35ZS-W', 'SRK35ZS-W'];
    }

    #[DataProvider('confusions')]
    public function testOtherReadingConfusionsAreCorrected(string $read, string $understood): void
    {
        $reference = ModelReference::fromInput($read);

        $this->assertSame($understood, $reference?->canonical);
        $this->assertTrue($reference->corrected);
    }

    public function testAReferenceSplitWithoutHelpIsNeverCorrected(): void
    {
        $reference = ModelReference::fromInput('SRK25ZS-W');

        $this->assertFalse($reference?->corrected);
        $this->assertSame('SRK25ZS-W', ModelReference::fromDocument('SRK25ZS-W')?->canonical);
        $this->assertSame('SRK2SZS-W', ModelReference::fromDocument('SRK2SZS-W')?->canonical);
        $this->assertFalse(ModelReference::fromDocument('SRK2SZS-W')->structured);
    }

    public function testASeriesWithoutSuffix(): void
    {
        $reference = ModelReference::fromInput('FDC224KXZME1');

        $this->assertSame(['FDC', '224', 'KXZME1', null], [$reference?->prefix, $reference->capacity, $reference->series, $reference->suffix]);
        $this->assertNull($reference->suffixBase());
    }

    public function testTheLongestPrefixWins(): void
    {
        $reference = ModelReference::fromInput('FDUT15KXE6F-W');

        $this->assertSame(['FDUT', '15', 'KXE6F', 'W'], [$reference?->prefix, $reference->capacity, $reference->series, $reference->suffix]);
        $this->assertSame('FDTC', ModelReference::fromInput('FDTC15KXZE1-W')?->prefix);
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function unstructured(): \Iterator
    {
        yield 'groupe FDCW sans puissance' => ['FDCW-VNX-A', 'FDCW-VNX-A'];
        yield 'télécommande' => ['rc-ex3d', 'RC-EX3D'];
        yield 'code Yack' => ['YORABTM200', 'YORABTM200'];
    }

    #[DataProvider('unstructured')]
    public function testReferencesWithoutKnownShapeStayUnstructured(string $input, string $canonical): void
    {
        $reference = ModelReference::fromInput($input);

        $this->assertSame($canonical, $reference?->canonical);
        $this->assertFalse($reference->structured);
        $this->assertFalse($reference->corrected);
        $this->assertSame([null, null, null, null], [$reference->prefix, $reference->capacity, $reference->series, $reference->suffix]);
    }

    public function testSpacesOtherThanBeforeASuffixAreRemoved(): void
    {
        $this->assertSame('SRK25ZS-W', ModelReference::fromInput('SRK 25 ZS W')?->canonical);
        $this->assertSame('RCEX3D', ModelReference::fromInput('RC EX3D')?->canonical);
        $this->assertSame('SRK25ZS', ModelReference::fromInput('SRK25 ZS')?->canonical);
    }

    public function testEmptyOrTooLongInputIsRefused(): void
    {
        $this->assertNull(ModelReference::fromInput(''));
        $this->assertNull(ModelReference::fromInput('   '));
        $this->assertNull(ModelReference::fromInput('Modèle : '));
        $this->assertNull(ModelReference::fromInput(str_repeat('A', 41)));
        $this->assertSame(str_repeat('A', 40), ModelReference::fromInput(str_repeat('A', 40))?->canonical);
    }

    public function testComparisons(): void
    {
        $read = ModelReference::fromInput('SRK25ZS-W');
        $variant = ModelReference::fromInput('SRK25ZS-WFT');
        $otherCapacity = ModelReference::fromInput('SRK50ZS-WF');
        $otherSeries = ModelReference::fromInput('SRK25ZSX-W');
        $otherType = ModelReference::fromInput('SRC25ZS-W');
        $unstructured = ModelReference::fromInput('RC-EX3D');
        \assert(null !== $read && null !== $variant && null !== $otherCapacity && null !== $otherSeries && null !== $otherType && null !== $unstructured);

        $this->assertSame('W', $variant->suffixBase());
        $this->assertTrue($read->is(ModelReference::fromInput('srk25zs w') ?? $variant));
        $this->assertFalse($read->is($variant));
        $this->assertTrue($read->sameModelAs($variant));
        $this->assertFalse($read->sameModelAs($otherCapacity));
        $this->assertTrue($read->sameSeriesAs($otherCapacity));
        $this->assertFalse($read->sameSeriesAs($otherSeries));
        $this->assertTrue($read->sameTypeAs($otherSeries));
        $this->assertFalse($read->sameTypeAs($otherType));
        $this->assertFalse($unstructured->sameTypeAs($unstructured));
        $this->assertFalse($unstructured->sameModelAs($unstructured));
    }
}
