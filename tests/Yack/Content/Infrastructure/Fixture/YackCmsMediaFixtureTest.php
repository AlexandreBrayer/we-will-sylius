<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Fixture;

use App\Entity\Content\CmsMedia;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Yack\Content\Domain\BlockType;
use App\Yack\Content\Infrastructure\Fixture\YackCmsMediaFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Reads the real historical visuals of the repository (public/yack).
 *
 * @internal
 */
#[CoversClass(YackCmsMediaFixture::class)]
final class YackCmsMediaFixtureTest extends TestCase
{
    public function testTheTwentyOneHistoricalVisualsAreRegisteredUnderTheirOwnPathInTheirOrder(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        $fixture = new YackCmsMediaFixture($library, \dirname(__DIR__, 5) . '/public');

        $fixture->load([]);

        $this->assertSame('yack_cms_media', $fixture->getName());
        $this->assertSame(BlockType::IMAGES, array_map(static fn (CmsMedia $media): string => $media->getPath(), $library->search()));
        $hero = $library->search('hero')[0];
        $this->assertSame(['hero.jpg', 'image/jpeg', 1600, 897, null, false], [$hero->getOriginalName(), $hero->getMediaType(), $hero->getWidth(), $hero->getHeight(), $hero->getAuthorName(), $hero->isUploaded()]);
        $this->assertSame(filesize(\dirname(__DIR__, 5) . '/public/yack/cms/hero.jpg'), $hero->getSize());
    }

    public function testAVisualAlreadyInTheLibraryIsLeftAsIs(): void
    {
        $library = new InMemoryCmsMediaLibrary();
        $fixture = new YackCmsMediaFixture($library, \dirname(__DIR__, 5) . '/public');
        $fixture->load([]);
        $first = $library->media;

        $fixture->load([]);

        $this->assertSame($first, $library->media);
    }

    public function testAMissingVisualStopsTheFixtures(): void
    {
        $fixture = new YackCmsMediaFixture(new InMemoryCmsMediaLibrary(), sys_get_temp_dir() . '/yack-sans-visuels');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Visuel historique introuvable : public/yack/cms/pac-monobloc.png.');

        $fixture->load([]);
    }
}
