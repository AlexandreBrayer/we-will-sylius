<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Infrastructure\Source\FeedRecordMapper;
use App\Yack\Catalog\Infrastructure\Source\JsonFileProductSource;
use App\Yack\Catalog\Infrastructure\Source\SimulatedSourceOutage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Feeds written to a temporary folder; the mapping of each record is FeedRecordMapper's (its own test).
 *
 * @internal
 */
#[CoversClass(JsonFileProductSource::class)]
#[CoversClass(SimulatedSourceOutage::class)]
final class JsonFileProductSourceTest extends TestCase
{
    private string $dir;

    private SimulatedSourceOutage $outage;

    #[\Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/yack-feed-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/images', 0o777, true);
        touch($this->dir . '/images/console.png');
        $this->outage = new SimulatedSourceOutage(new ArrayAdapter());
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

    public function testReadsTheRecordsAndKeepsThemAsReceived(): void
    {
        $feed = $this->read([$this->record()]);

        $this->assertSame('catalog.json (2026-11-07T06:00:00+01:00)', $feed->origin);
        $this->assertSame([], $feed->rejected);
        $this->assertCount(1, $feed->products);
        $this->assertSame('SRF50ZSX-W', $feed->products[0]->code);
        $this->assertSame($this->dir . '/images/console.png', $feed->products[0]->imagePath);
        $this->assertSame(['SRF50ZSX-W' => $this->record()], $feed->payloads);
        $this->assertSame([], $feed->lines);
    }

    public function testRejectsAMalformedRecordWithoutBlockingTheOthers(): void
    {
        $feed = $this->read([
            $this->record(['sku' => 'RC-E5', 'type' => 'kit']),
            'pas un objet',
            $this->record(),
        ]);

        $this->assertCount(1, $feed->products);
        $this->assertCount(2, $feed->rejected);
        $this->assertSame('RC-E5', $feed->rejected[0]->reference);
        $this->assertSame('Produit « RC-E5 » : nature « kit » inconnue.', $feed->rejected[0]->reason);
        $this->assertSame($this->record(['sku' => 'RC-E5', 'type' => 'kit']), $feed->rejected[0]->payload);
        $this->assertSame('#2', $feed->rejected[1]->reference);
        $this->assertSame('Champ « sku » absent ou vide.', $feed->rejected[1]->reason);
    }

    #[DataProvider('provideUnreadableFeeds')]
    public function testUnreadableFeed(?string $content, string $message): void
    {
        if (null !== $content) {
            file_put_contents($this->dir . '/catalog.json', $content);
        }

        $this->expectException(ProductSourceUnavailable::class);
        $this->expectExceptionMessage($message);

        $this->source()->fetch($this->dir . '/catalog.json');
    }

    /**
     * @return \Iterator<string, array{string|null, string}>
     */
    public static function provideUnreadableFeeds(): \Iterator
    {
        yield 'absent' => [null, 'illisible.'];
        yield 'not JSON' => ['{', 'JSON invalide (Syntax error).'];
        yield 'no products' => ['{"exportedAt": "2026-11-07"}', 'liste « products » absente.'];
    }

    public function testTheSimulatedOutageMakesTheSourceUnavailable(): void
    {
        $this->read([$this->record()]);
        $this->outage->switch(true);

        $this->assertTrue($this->outage->isOn());
        $this->expectException(ProductSourceUnavailable::class);
        $this->expectExceptionMessage(JsonFileProductSource::SIMULATED_OUTAGE);

        $this->source()->fetch($this->dir . '/catalog.json');
    }

    public function testTheOutageCanBeSwitchedOff(): void
    {
        $this->outage->switch(true);
        $this->outage->switch(false);

        $this->assertFalse($this->outage->isOn());
        $this->assertCount(1, $this->read([$this->record()])->products);
    }

    public function testHealthOfAReadableFeed(): void
    {
        $this->read([$this->record(), $this->record(['sku' => 'RC-E5'])]);

        $health = $this->source()->check();

        $this->assertTrue($health->available);
        $this->assertFalse($health->simulatedOutage);
        $this->assertSame('Flux catalog.json (2026-11-07T06:00:00+01:00) joignable : 2 enregistrements disponibles.', $health->message);
    }

    public function testHealthOfAnUnreadableFeed(): void
    {
        $health = $this->source()->check();

        $this->assertFalse($health->available);
        $this->assertFalse($health->simulatedOutage);
        $this->assertSame(sprintf('Flux Yack « %s/catalog.json » illisible.', $this->dir), $health->message);
    }

    public function testHealthDuringTheSimulatedOutage(): void
    {
        $this->read([$this->record()]);
        $this->outage->switch(true);

        $health = $this->source()->check();

        $this->assertFalse($health->available);
        $this->assertTrue($health->simulatedOutage);
        $this->assertSame(JsonFileProductSource::SIMULATED_OUTAGE, $health->message);
    }

    public function testAFeedRelativeToTheProject(): void
    {
        $this->read([$this->record()]);

        $source = new JsonFileProductSource(new FeedRecordMapper(), $this->outage, $this->dir, 'catalog.json');

        $this->assertCount(1, $source->fetch('catalog.json')->products);
    }

    private function source(): JsonFileProductSource
    {
        return new JsonFileProductSource(new FeedRecordMapper(), $this->outage, '/nulle-part', $this->dir . '/catalog.json');
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
            'price' => ['amount' => '990.90', 'currency' => 'EUR'],
            'stock' => 13,
            'fallback_image' => 'images/console.png',
        ];
    }

    /** @param list<mixed> $records */
    private function read(array $records): ProductFeed
    {
        file_put_contents($this->dir . '/catalog.json', json_encode(['exportedAt' => '2026-11-07T06:00:00+01:00', 'products' => $records], \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION));

        return $this->source()->fetch($this->dir . '/catalog.json');
    }
}
