<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\Charte;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Charte::class)]
final class CharteTest extends TestCase
{
    public function testExposesTheNamedColoursOfTheCharte(): void
    {
        $keys = array_keys(Charte::COLORS);

        foreach (['magenta', 'magenta-dark', 'plum', 'teal', 'teal-dark', 'teal-deep', 'yellow', 'ochre', 'dark', 'gray', 'light', 'white', 'black'] as $key) {
            $this->assertContains($key, $keys);
        }
        foreach (Charte::COLORS as [$label, $value]) {
            $this->assertNotSame('', $label);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $value);
        }
    }

    public function testExposesTitleSizesSpacingsAndRadii(): void
    {
        $this->assertSame(['s', 'm', 'l', 'xl'], array_keys(Charte::TITLE_SIZES));
        $this->assertSame(['0', 's', 'm', 'l', 'xl'], array_map('strval', array_keys(Charte::SPACINGS)));
        $this->assertSame(['aucun', 'petit', 'moyen', 'grand'], array_keys(Charte::RADII));
    }

    public function testToArrayListsEveryFamilyAsKeyLabelValue(): void
    {
        $charte = Charte::toArray();

        $this->assertSame(['colors', 'titleSizes', 'spacings', 'radii'], array_keys($charte));
        $this->assertCount(count(Charte::COLORS), $charte['colors']);
        $this->assertSame(['key' => 'teal', 'label' => 'Turquoise', 'value' => '#82cec8'], $charte['colors'][3]);
        $this->assertSame(['key' => '0', 'label' => 'Aucun', 'value' => '0px'], $charte['spacings'][0]);
        $this->assertSame(['key' => 'xl', 'label' => 'Très grand', 'value' => '56px'], $charte['titleSizes'][3]);
        $this->assertSame(['key' => 'grand', 'label' => 'Grand', 'value' => '1rem'], $charte['radii'][3]);
    }

    public function testColorKeyFindsACharteColourWhateverTheCase(): void
    {
        $this->assertSame('teal', Charte::colorKey('#82CEC8'));
        $this->assertSame('white', Charte::colorKey('#ffffff'));
        $this->assertNull(Charte::colorKey('#ff7700'));
    }
}
