<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Infrastructure\Source\JsonFileProductSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Feeds written to a temporary folder (the export and its images), products without brand
 * so that the brand taxon code (Symfony AsciiSlugger) stays out of these cases.
 *
 * @internal
 */
#[CoversClass(JsonFileProductSource::class)]
final class JsonFileProductSourceTest extends TestCase
{
    private string $dir;

    #[\Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/yack-feed-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/images/reel', 0o777, true);
        touch($this->dir . '/images/console.png');
    }

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
        $feed = $this->read([$this->record()]);

        $this->assertSame([], $feed->rejected);
        $this->assertCount(1, $feed->products);
        $product = $feed->products[0];
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
    }

    public function testTakesThePhotoWhenItsFileIsThere(): void
    {
        touch($this->dir . '/images/reel/SRF50ZSX-W.jpg');

        $product = $this->read([$this->record()])->products[0];

        $this->assertSame($this->dir . '/images/reel/SRF50ZSX-W.jpg', $product->imagePath);
        $this->assertSame($this->dir . '/images/console.png', $product->fallbackImagePath);
        $this->assertTrue($product->hasPhoto());
    }

    public function testTakesTheFallbackVisualWithoutThePhoto(): void
    {
        $product = $this->read([$this->record()])->products[0];

        $this->assertSame($this->dir . '/images/console.png', $product->imagePath);
        $this->assertSame($this->dir . '/images/console.png', $product->fallbackImagePath);
        $this->assertFalse($product->hasPhoto());
    }

    public function testTakesTheFallbackVisualWithoutPhotoDeclared(): void
    {
        $record = $this->record();
        unset($record['image']);

        $this->assertSame($this->dir . '/images/console.png', $this->read([$record])->products[0]->imagePath);
    }

    public function testKeepsTheDeclaredPhotoWhenNoFileExists(): void
    {
        $record = $this->record(['fallback_image' => 'images/inconnu.png']);

        $product = $this->read([$record])->products[0];

        $this->assertSame($this->dir . '/images/reel/SRF50ZSX-W.jpg', $product->imagePath);
        $this->assertNull($product->fallbackImagePath);
    }

    public function testWithoutAnyImage(): void
    {
        $record = $this->record();
        unset($record['image'], $record['fallback_image']);

        $this->assertNull($this->read([$record])->products[0]->imagePath);
    }

    public function testOptionalFieldsLeaveNoAttribute(): void
    {
        $record = $this->record(['model' => null, 'sectors' => [], 'documents' => [], 'specs' => []]);
        unset($record['range'], $record['typology']);

        $this->assertSame([], $this->read([$record])->products[0]->attributes);
    }

    public function testRejectsAMalformedRecordWithoutBlockingTheOthers(): void
    {
        $feed = $this->read([
            $this->record(['sku' => 'RC-E5', 'type' => 'kit']),
            $this->record(['sku' => 'RC-EX3D', 'specs' => ['scop_froid' => 5.1]]),
            $this->record(['sku' => 'RC-ES1', 'price' => ['amount' => '74.90', 'currency' => 'USD']]),
            $this->record(),
        ]);

        $this->assertCount(1, $feed->products);
        $this->assertCount(3, $feed->rejected);
        $this->assertSame('RC-E5', $feed->rejected[0]->reference);
        $this->assertSame('Product "RC-E5": unknown type "kit".', $feed->rejected[0]->reason);
        $this->assertSame('Product "RC-EX3D": unknown spec "scop_froid".', $feed->rejected[1]->reason);
        $this->assertSame('Product "RC-ES1": unsupported currency "USD".', $feed->rejected[2]->reason);
    }

    public function testUnreadableFeed(): void
    {
        $this->expectException(ProductSourceUnavailable::class);

        (new JsonFileProductSource($this->dir))->fetch('absent.json');
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

    /** @param list<array<string, mixed>> $records */
    private function read(array $records): ProductFeed
    {
        file_put_contents($this->dir . '/catalog.json', json_encode(['exportedAt' => '2026-11-07T06:00:00+01:00', 'products' => $records], \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION));

        return (new JsonFileProductSource($this->dir))->fetch($this->dir . '/catalog.json');
    }
}
