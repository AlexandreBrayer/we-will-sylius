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

    public function testEveryImagePropHasItsAlternativeTextFramingAndFocalPoint(): void
    {
        $this->assertSame(
            ['imageAlt' => null, 'imageFit' => ['', 'couvrir', 'contenir'], 'imageFocus' => ['', 'haut', 'centre', 'bas']],
            BlockType::TextImage->imageSettingProps(),
        );
        $this->assertSame(
            ['image1Alt', 'image1Fit', 'image1Focus', 'image2Alt', 'image2Fit', 'image2Focus', 'image3Alt', 'image3Fit', 'image3Focus'],
            array_keys(BlockType::Support->imageSettingProps()),
        );
        $this->assertSame([], BlockType::Faq->imageSettingProps());
        foreach (BlockType::cases() as $type) {
            $this->assertSame($type->imageSettingProps(), array_intersect_key($type->props(), $type->imageSettingProps()), $type->value);
            $this->assertCount(3 * count($type->imageProps()), $type->imageSettingProps(), $type->value);
        }
    }

    public function testAnAlternativeTextIsOneLineWithoutHtmlAndAsShortAsTheOneOfTheLibrary(): void
    {
        $this->assertSame(['image1Alt', 'image2Alt', 'image3Alt'], BlockType::Support->imageAltProps());
        $this->assertSame([], BlockType::Faq->imageAltProps());
        $this->assertTrue(BlockType::isImageAlt(''));
        $this->assertTrue(BlockType::isImageAlt('Salon « chauffé » & climatisé'));
        $this->assertTrue(BlockType::isImageAlt(str_repeat('é', 255)));
        $this->assertFalse(BlockType::isImageAlt(str_repeat('é', 256)));
        $this->assertFalse(BlockType::isImageAlt('<b>gras</b>'));
        $this->assertFalse(BlockType::isImageAlt('a > b'));
        $this->assertFalse(BlockType::isImageAlt("Deux\nlignes"));
        $this->assertFalse(BlockType::isImageAlt("tab\tulation"));
        $this->assertFalse(BlockType::isImageAlt("\xff\xfe"));
    }

    public function testAnImageWithoutSettingsKeepsTheFramingOfItsBlock(): void
    {
        $this->assertSame('', BlockType::IMAGE_FITS[0]);
        $this->assertSame('', BlockType::IMAGE_FOCUSES[0]);
    }
}
