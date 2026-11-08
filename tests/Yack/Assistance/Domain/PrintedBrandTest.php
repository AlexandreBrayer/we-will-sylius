<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Domain;

use App\Yack\Assistance\Domain\PrintedBrand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PrintedBrand::class)]
final class PrintedBrandTest extends TestCase
{
    /** @return \Iterator<string, array{string, string}> */
    public static function printed(): \Iterator
    {
        yield 'Yack en capitales' => ['YACK', 'Yack'];
        yield 'MHI en minuscules' => ['mhi', 'MHI'];
        yield 'MHI tel quel' => ['MHI', 'MHI'];
        yield 'RHOSS' => ['Rhoss', 'RHOSS'];
        yield 'Hydrolia' => ['HYDROLIA', 'Hydrolia'];
        yield 'espaces autour' => ['  Yack ', 'Yack'];
        yield 'raison sociale MHI' => ['Mitsubishi Heavy Industries', 'MHI'];
        yield 'raison sociale MHI en capitales, Ltd.' => ['MITSUBISHI HEAVY INDUSTRIES, LTD.', 'MHI'];
        yield 'raison sociale de la filiale' => ['Mitsubishi Heavy Industries Thermal Systems, Ltd.', 'MHI'];
        yield 'Mitsubishi Electric, autre marque' => ['MITSUBISHI ELECTRIC', 'MITSUBISHI ELECTRIC'];
        yield 'Mitsubishi seul, ambigu' => ['MITSUBISHI', 'MITSUBISHI'];
        yield 'marque hors catalogue' => ['DAIKIN', 'DAIKIN'];
        yield 'nom voisin, jamais rapproché' => ['YAK', 'YAK'];
    }

    #[DataProvider('printed')]
    public function testAPrintedBrandTakesTheCatalogNameOnlyWhenItIsTheSame(string $printed, string $name): void
    {
        $this->assertSame($name, PrintedBrand::name($printed));
    }

    public function testTheBrandsAreThoseOfTheCatalog(): void
    {
        $catalog = json_decode((string) file_get_contents(\dirname(__DIR__, 4).'/data/yack/catalog.json'), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($catalog) && \is_array($catalog['products'] ?? null));
        $brands = array_values(array_unique(array_filter(array_column($catalog['products'], 'brand'), 'is_string')));
        sort($brands);
        $expected = PrintedBrand::CATALOG;
        sort($expected);

        $this->assertSame($expected, $brands);
    }
}
