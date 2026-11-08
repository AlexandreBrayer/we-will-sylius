<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\BlockType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlockType::class)]
final class BlockTypeTest extends TestCase
{
    public function testTheImagePropsAreExactlyThePropsThatOfferTheHistoricalVisuals(): void
    {
        foreach (BlockType::cases() as $type) {
            $offering = array_keys(array_filter($type->props(), static fn (?array $choices): bool => null !== $choices && [] === array_diff(BlockType::IMAGES, $choices)));

            $this->assertSame($offering, $type->imageProps(), $type->value);
        }
    }

    public function testTheImagePropsOfTheBlocksWithVisuals(): void
    {
        $this->assertSame(['image'], BlockType::Hero->imageProps());
        $this->assertSame(['image'], BlockType::TextImage->imageProps());
        $this->assertSame(['image1', 'image2', 'image3', 'image4', 'image5'], BlockType::Tiles->imageProps());
        $this->assertSame(['image1', 'image2', 'image3'], BlockType::Support->imageProps());
        $this->assertSame([], BlockType::Faq->imageProps());
    }

    public function testTheTwentyOneHistoricalVisualsAreFilesOfThePublicDir(): void
    {
        $this->assertCount(21, BlockType::IMAGES);
        foreach (BlockType::IMAGES as $path) {
            $this->assertFileExists(\dirname(__DIR__, 4) . '/public/' . $path);
        }
    }
}
