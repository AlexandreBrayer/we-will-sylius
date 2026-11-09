<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Yack\Catalog\Application\AttachProductPhotos;
use App\Yack\Catalog\Application\SearchIndexerInterface;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductPhotos;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\SourceProduct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AttachProductPhotos::class)]
final class AttachProductPhotosTest extends TestCase
{
    private AttachProductPhotosFakePhotos $photos;

    private AttachProductPhotosFakeIndexer $indexer;

    #[\Override]
    protected function setUp(): void
    {
        $this->photos = new AttachProductPhotosFakePhotos();
        $this->indexer = new AttachProductPhotosFakeIndexer();
    }

    public function testOnlyProductsWithAPhotoFileAreHandedToTheCatalog(): void
    {
        $result = $this->useCase(new ProductFeed('catalog.json', [
            self::product('SRF50ZSX-W', '/d/images/reel/SRF50ZSX-W.jpg', '/d/images/console.png'),
            self::product('RC-E5', '/d/images/part-remote.png', '/d/images/part-remote.png'),
            self::product('YK-FAN', '/d/images/part-fan.png', null),
            self::product('SRK20ZS-W', '/d/images/reel/SRK20ZS-W.jpg', '/d/images/split-mural.png'),
        ]))('data/yack/catalog.json');

        $this->assertSame([
            ['SRF50ZSX-W', '/d/images/reel/SRF50ZSX-W.jpg', '/d/images/console.png'],
            ['SRK20ZS-W', '/d/images/reel/SRK20ZS-W.jpg', '/d/images/split-mural.png'],
        ], $this->photos->calls);
        $this->assertSame(['SRF50ZSX-W'], $result->attached);
        $this->assertSame(1, $result->alreadyRight);
        $this->assertSame(2, $result->withoutPhoto);
        $this->assertSame([['SRF50ZSX-W']], $this->indexer->refreshed);
    }

    public function testNothingAttachedLeavesTheSearchIndexAlone(): void
    {
        $result = $this->useCase(new ProductFeed('catalog.json', [
            self::product('SRK20ZS-W', '/d/images/reel/SRK20ZS-W.jpg', '/d/images/split-mural.png'),
        ]))('data/yack/catalog.json');

        $this->assertSame([], $result->attached);
        $this->assertSame(1, $result->alreadyRight);
        $this->assertSame([], $this->indexer->refreshed);
    }

    public function testAnUnreadableFeedTouchesNothing(): void
    {
        $this->expectException(ProductSourceUnavailable::class);

        try {
            $this->useCase(null)('absent.json');
        } finally {
            $this->assertSame([], $this->photos->calls);
        }
    }

    private function useCase(?ProductFeed $feed): AttachProductPhotos
    {
        return new AttachProductPhotos(new AttachProductPhotosFakeSource($feed), $this->photos, $this->indexer);
    }

    private static function product(string $code, ?string $image, ?string $fallback): SourceProduct
    {
        return new SourceProduct($code, 'Produit ' . $code, true, ProductKind::Finished, 'pac_air_air', 99090, 13, imagePath: $image, fallbackImagePath: $fallback);
    }
}

/** Hand-written fake of the port: the given feed, or unavailable when there is none. */
final class AttachProductPhotosFakeSource implements ProductSource
{
    public function __construct(private readonly ?ProductFeed $feed)
    {
    }

    public function fetch(string $feed): ProductFeed
    {
        return $this->feed ?? throw new ProductSourceUnavailable(sprintf('Yack feed "%s" is not readable.', $feed));
    }
}

/** Hand-written fake of the port: records the calls, replaces only SRF50ZSX-W (the others already have their photo). */
final class AttachProductPhotosFakePhotos implements ProductPhotos
{
    /** @var list<array{string, string, string}> */
    public array $calls = [];

    public function replaceFallback(string $productCode, string $photoPath, string $fallbackPath): bool
    {
        $this->calls[] = [$productCode, $photoPath, $fallbackPath];

        return 'SRF50ZSX-W' === $productCode;
    }
}

/** Hand-written fake of the interface: records the refreshed codes. */
final class AttachProductPhotosFakeIndexer implements SearchIndexerInterface
{
    /** @var list<list<string>> */
    public array $refreshed = [];

    public function reindexAll(): array
    {
        return ['products' => 0, 'categories' => 0];
    }

    public function refresh(array $codes): void
    {
        $this->refreshed[] = $codes;
    }
}
