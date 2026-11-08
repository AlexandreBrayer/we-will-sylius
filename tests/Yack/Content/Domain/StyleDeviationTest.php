<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\StyleDeviation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StyleDeviation::class)]
final class StyleDeviationTest extends TestCase
{
    #[DataProvider('provideDeviations')]
    public function testDescribesTheValueInASentence(string $property, string $value, string $label, string $sentence): void
    {
        $deviation = new StyleDeviation(2, $property, $value);

        $this->assertSame(2, $deviation->block);
        $this->assertSame($label, $deviation->propertyLabel());
        $this->assertSame($sentence, $deviation->describe());
    }

    /**
     * @return \Iterator<string, array{string, string, string, string}>
     */
    public static function provideDeviations(): \Iterator
    {
        yield 'colour' => ['fond', '#ff7700', 'Fond', 'la couleur #ff7700'];
        yield 'title size' => ['titre', '40px', 'Taille du titre', 'la taille de titre 40px'];
        yield 'spacing' => ['basEspace', '120px', 'Espace en dessous', 'l\'espacement 120px'];
    }
}
