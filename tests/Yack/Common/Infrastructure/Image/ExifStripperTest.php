<?php

declare(strict_types=1);

namespace App\Tests\Yack\Common\Infrastructure\Image;

use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Infrastructure\Image\ExifStripper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExifStripper::class)]
final class ExifStripperTest extends TestCase
{
    private ExifStripper $stripper;

    #[\Override]
    protected function setUp(): void
    {
        $this->stripper = new ExifStripper();
    }

    public function testJpegLosesItsExif(): void
    {
        $withExif = self::withExifSegment(self::jpeg(40, 30));
        $this->assertStringContainsString('Exif', $withExif);

        $input = $this->stripper->toInput($withExif);

        $this->assertSame(AiInput::IMAGE, $input->kind);
        $this->assertSame('image/jpeg', $input->mediaType);
        $this->assertFalse(str_contains($input->content, 'Exif'));
        $this->assertFalse(str_contains($input->content, 'GPS-SECRET'));
    }

    public function testPngStaysPng(): void
    {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $this->assertSame('image/png', $this->stripper->toInput($png)->mediaType);
    }

    public function testLargePhotoIsReduced(): void
    {
        $input = $this->stripper->toInput(self::jpeg(3200, 1200));
        $size = getimagesizefromstring($input->content);

        $this->assertSame(ExifStripper::MAX_SIDE, $size[0]);
        $this->assertSame(600, $size[1]);
    }

    public function testOtherFormatsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JPEG ou PNG');
        $this->stripper->toInput('GIF89a not really');
    }

    public function testEmptyIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->stripper->toInput('');
    }

    private static function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    /** Inserts an APP1 "Exif" segment right after the JPEG SOI marker. */
    private static function withExifSegment(string $jpeg): string
    {
        $payload = "Exif\0\0GPS-SECRET 43.12N 6.13E";
        $segment = "\xFF\xE1".pack('n', \strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
