<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Console;

use App\Yack\Catalog\Application\AttachProductPhotos;
use App\Yack\Catalog\Application\SearchIndexerInterface;
use App\Yack\Catalog\Domain\ProductFeed;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Domain\ProductPhotos;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;
use App\Yack\Catalog\Domain\SourceProduct;
use App\Yack\Catalog\Infrastructure\Console\AttachProductPhotosCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(AttachProductPhotosCommand::class)]
final class AttachProductPhotosCommandTest extends TestCase
{
    public function testPrintsTheSummaryLine(): void
    {
        $source = $this->createStub(ProductSource::class);
        $source->method('fetch')->willReturn(new ProductFeed('catalog.json', [
            new SourceProduct('SRF50ZSX-W', 'Console', true, ProductKind::Finished, 'pac', 99090, 13, imagePath: '/d/reel/SRF50ZSX-W.jpg', fallbackImagePath: '/d/console.png'),
            new SourceProduct('YK-FAN', 'Ventilateur', true, ProductKind::SparePart, 'pieces', 4900, 3, imagePath: '/d/part-fan.png'),
        ]));
        $photos = $this->createStub(ProductPhotos::class);
        $photos->method('replaceFallback')->willReturn(true);
        $tester = $this->tester($source, $photos);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame("photos rattachées aux produits : 1, déjà en place : 0, sans photo sur le disque : 1\n", $tester->getDisplay());
    }

    public function testAnUnreadableFeedFails(): void
    {
        $source = $this->createStub(ProductSource::class);
        $source->method('fetch')->willThrowException(new ProductSourceUnavailable('Yack feed "absent.json" is not readable.'));
        $tester = $this->tester($source, $this->createStub(ProductPhotos::class));

        $this->assertSame(Command::FAILURE, $tester->execute(['feed' => 'absent.json']));
        $this->assertStringContainsString('photos : flux illisible, Yack feed "absent.json" is not readable.', $tester->getDisplay());
    }

    public function testAnEmptyFeedArgumentIsInvalid(): void
    {
        $tester = $this->tester($this->createStub(ProductSource::class), $this->createStub(ProductPhotos::class));

        $this->assertSame(Command::INVALID, $tester->execute(['feed' => '']));
    }

    private function tester(ProductSource $source, ProductPhotos $photos): CommandTester
    {
        return new CommandTester(new AttachProductPhotosCommand(new AttachProductPhotos($source, $photos, $this->createStub(SearchIndexerInterface::class))));
    }
}
