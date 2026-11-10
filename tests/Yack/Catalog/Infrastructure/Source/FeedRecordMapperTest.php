<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\InvalidSourceProduct;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\SourceProduct;
use App\Yack\Catalog\Infrastructure\Source\FeedRecordMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Images written to a temporary folder, products without brand unless stated so that the brand taxon
 * code (Symfony AsciiSlugger) stays out of most cases.
 *
 * @internal
 */
#[CoversClass(FeedRecordMapper::class)]
final class FeedRecordMapperTest extends TestCase
{
    private string $dir;

    #[\Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/yack-mapper-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/images/reel', 0o777, true);
        touch($this->dir . '/images/console.png');
    }

    #[\Override]
    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    public function testMapsARealProductRecord(): void
    {
        $product = $this->map($this->record());

        $this->assertSame('SRF50ZSX-W', $product->code);
        $this->assertSame('Console Double Flux - 5 kW - R32', $product->name);
        $this->assertTrue($product->active);
        $this->assertSame(ProductKind::Finished, $product->kind);
        $this->assertSame('pac_air_air_residentiel_ui_console', $product->mainTaxonCode);
        $this->assertSame(99090, $product->priceInCents);
        $this->assertSame(13, $product->stock);
        $this->assertSame([], $product->taxonCodes);
        $this->assertSame([
            'puissance_kw' => 5.0,
            'fluide_frigorigene' => 'R32',
            'gamme' => 'Pompe à Chaleur Air/Air',
            'modele' => 'SRF50',
            'typologie' => 'Console',
            'secteurs' => 'Résidentiel, Petit Tertiaire',
            'documentation' => 'Documentation produit, Notice diagnostic',
        ], $product->attributes);
        $this->assertSame('Installation murale ou au sol.', $product->shortDescription);
        $this->assertSame("- Installation murale ou au sol\n- Très faible niveau sonore", $product->description);
    }

    public function testTheBrandIsAnAttributeAndATaxon(): void
    {
        $product = $this->map($this->record(['brand' => 'Mitsubishi Heavy']));

        $this->assertSame(['marque_mitsubishi_heavy'], $product->taxonCodes);
        $this->assertSame('Mitsubishi Heavy', $product->attributes['marque']);
    }

    public function testAnInactiveSparePart(): void
    {
        $product = $this->map($this->record(['status' => 'inactive', 'type' => 'spare_part']));

        $this->assertFalse($product->active);
        $this->assertSame(ProductKind::SparePart, $product->kind);
    }

    public function testTakesThePhotoWhenItsFileIsThere(): void
    {
        touch($this->dir . '/images/reel/SRF50ZSX-W.jpg');

        $product = $this->map($this->record());

        $this->assertSame($this->dir . '/images/reel/SRF50ZSX-W.jpg', $product->imagePath);
        $this->assertSame($this->dir . '/images/console.png', $product->fallbackImagePath);
        $this->assertTrue($product->hasPhoto());
    }

    public function testTakesTheFallbackVisualWithoutThePhoto(): void
    {
        $product = $this->map($this->record());

        $this->assertSame($this->dir . '/images/console.png', $product->imagePath);
        $this->assertSame($this->dir . '/images/console.png', $product->fallbackImagePath);
        $this->assertFalse($product->hasPhoto());
    }

    public function testTakesTheFallbackVisualWithoutPhotoDeclared(): void
    {
        $record = $this->record();
        unset($record['image']);

        $this->assertSame($this->dir . '/images/console.png', $this->map($record)->imagePath);
    }

    public function testKeepsTheDeclaredPhotoWhenNoFileExists(): void
    {
        $product = $this->map($this->record(['fallback_image' => 'images/inconnu.png']));

        $this->assertSame($this->dir . '/images/reel/SRF50ZSX-W.jpg', $product->imagePath);
        $this->assertNull($product->fallbackImagePath);
    }

    public function testWithoutAnyImage(): void
    {
        $record = $this->record();
        unset($record['image'], $record['fallback_image']);

        $this->assertNull($this->map($record)->imagePath);
    }

    public function testImagesAreLeftOutWithoutImageDirectory(): void
    {
        $product = (new FeedRecordMapper())->map($this->record());

        $this->assertNull($product->imagePath);
        $this->assertNull($product->fallbackImagePath);
    }

    public function testOptionalFieldsLeaveNoAttribute(): void
    {
        $record = $this->record(['model' => null, 'sectors' => [], 'documents' => [], 'specs' => []]);
        unset($record['range'], $record['typology']);

        $this->assertSame([], $this->map($record)->attributes);
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('provideInvalidRecords')]
    public function testRejectsAnInvalidRecord(array $override, string $message): void
    {
        $this->expectException(InvalidSourceProduct::class);
        $this->expectExceptionMessage($message);

        $this->map($this->record($override));
    }

    /**
     * @return \Iterator<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidRecords(): \Iterator
    {
        yield 'unknown type' => [['type' => 'kit'], 'Produit « SRF50ZSX-W » : nature « kit » inconnue.'];
        yield 'unknown spec' => [['specs' => ['scop_froid' => 5.1]], 'Produit « SRF50ZSX-W » : caractéristique « scop_froid » inconnue.'];
        yield 'other currency' => [['price' => ['amount' => '74.90', 'currency' => 'USD']], 'Produit « SRF50ZSX-W » : devise « USD » non prise en charge.'];
        yield 'no price' => [['price' => null], 'Produit « SRF50ZSX-W » : prix absent ou invalide.'];
        yield 'price not a number' => [['price' => ['amount' => 'gratuit', 'currency' => 'EUR']], 'Produit « SRF50ZSX-W » : prix absent ou invalide.'];
        yield 'no name' => [['label' => ' '], 'Champ « label » absent ou vide.'];
    }

    /** @param array<string, mixed> $record */
    private function map(array $record): SourceProduct
    {
        return (new FeedRecordMapper())->map($record, $this->dir);
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function record(array $override = []): array
    {
        return $override + [
            'sku' => 'SRF50ZSX-W',
            'label' => 'Console Double Flux - 5 kW - R32',
            'status' => 'active',
            'type' => 'finished',
            'category' => 'pac_air_air_residentiel_ui_console',
            'range' => 'Pompe à Chaleur Air/Air',
            'price' => ['amount' => '990.90', 'currency' => 'EUR'],
            'stock' => 13,
            'specs' => ['power_kw' => 5.0, 'refrigerant' => 'R32'],
            'image' => 'images/reel/SRF50ZSX-W.jpg',
            'fallback_image' => 'images/console.png',
            'short_description' => 'Installation murale ou au sol.',
            'description' => "- Installation murale ou au sol\n- Très faible niveau sonore",
            'typology' => 'Console',
            'sectors' => ['Résidentiel', 'Petit Tertiaire'],
            'model' => 'SRF50',
            'family' => ['SRF25ZS-W', 'SRF35ZS-W', 'SRF50ZSX-W'],
            'documents' => ['Documentation produit', 'Notice diagnostic'],
            'ean' => '370000000000',
            'source_id' => '10000',
        ];
    }
}
