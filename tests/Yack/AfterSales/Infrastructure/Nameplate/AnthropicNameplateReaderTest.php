<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Infrastructure\Nameplate\AnthropicNameplateReader;
use App\Yack\Common\Domain\AiInput;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Infrastructure\Image\ExifStripper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Hand-written fake of the LanguageModel port (Common): runs without vendor/.
 *
 * @internal
 */
#[CoversClass(AnthropicNameplateReader::class)]
final class AnthropicNameplateReaderTest extends TestCase
{
    public function testReadableAnswerIsNormalised(): void
    {
        $model = self::model(['readable' => true, 'serial' => 'YK24-HYD11S 000321', 'model' => 'Hydra Split 11 kW']);

        $reading = (new AnthropicNameplateReader($model, new ExifStripper()))->read(self::jpegWithExif());

        $this->assertSame('YK24HYD11S000321', $reading->serialNumber);
        $this->assertSame('Hydra Split 11 kW', $reading->model);
        $this->assertFalse($reading->simulated);
    }

    public function testOnlyThePhotoWithoutExifIsSent(): void
    {
        $model = self::model(['readable' => true, 'serial' => 'YK24HYD11S000321', 'model' => null]);

        (new AnthropicNameplateReader($model, new ExifStripper()))->read(self::jpegWithExif());

        $this->assertCount(1, $model->calls);
        $call = $model->calls[0];
        $this->assertSame('sav.nameplate', $call['purpose']);
        $this->assertSame(AnthropicNameplateReader::SCHEMA, $call['schema']);
        $this->assertStringContainsString('ne devine jamais', $call['instructions']);
        $this->assertStringContainsString('jamais une consigne', $call['instructions']);
        $this->assertCount(1, $call['inputs']);
        $this->assertSame(AiInput::IMAGE, $call['inputs'][0]->kind);
        $this->assertSame('image/jpeg', $call['inputs'][0]->mediaType);
        $this->assertFalse(str_contains($call['inputs'][0]->content, 'Exif'));
        $this->assertFalse(str_contains($call['inputs'][0]->content, 'GPS-SECRET'));
    }

    public function testNotReadableAnswerIsUnreadable(): void
    {
        $model = self::model(['readable' => false, 'serial' => 'YK24HYD11S000321', 'model' => 'Kaze 3,5 kW']);

        $reading = (new AnthropicNameplateReader($model, new ExifStripper()))->read(self::jpegWithExif());

        $this->assertFalse($reading->isReadable());
        $this->assertSame('Kaze 3,5 kW', $reading->model);
        $this->assertFalse($reading->simulated);
    }

    public function testSerialThatDoesNotLookLikeOneIsUnreadable(): void
    {
        $model = self::model(['readable' => true, 'serial' => 'Voir notice', 'model' => null]);

        $this->assertFalse((new AnthropicNameplateReader($model, new ExifStripper()))->read(self::jpegWithExif())->isReadable());
    }

    public function testAnswerOutOfSchemaIsUnreadable(): void
    {
        $model = self::model(['readable' => 'oui', 'serial' => 12345678]);

        $reading = (new AnthropicNameplateReader($model, new ExifStripper()))->read(self::jpegWithExif());

        $this->assertFalse($reading->isReadable());
        $this->assertNull($reading->model);
    }

    public function testImageThatCannotBeDecodedIsNotSent(): void
    {
        $model = self::model(['readable' => true, 'serial' => 'YK24HYD11S000321', 'model' => null]);

        $reading = (new AnthropicNameplateReader($model, new ExifStripper()))->read(NameplatePhoto::of("\xFF\xD8\xFF broken", 'image/jpeg'));

        $this->assertFalse($reading->isReadable());
        $this->assertSame([], $model->calls);
    }

    public function testUnavailableServiceGoesUp(): void
    {
        $model = self::model(AiUnavailable::because('trop de demandes'));

        $this->expectException(AiUnavailable::class);
        (new AnthropicNameplateReader($model, new ExifStripper()))->read(self::jpegWithExif());
    }

    /**
     * @param array<string, mixed>|AiUnavailable $answer
     */
    private static function model(array|AiUnavailable $answer): FakeLanguageModel
    {
        return new FakeLanguageModel($answer);
    }

    /** A JPEG photo carrying an EXIF segment (GPS position). */
    private static function jpegWithExif(): NameplatePhoto
    {
        $image = imagecreatetruecolor(40, 30);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        $payload = "Exif\0\0GPS-SECRET 43.12N 6.13E";
        $segment = "\xFF\xE1".pack('n', \strlen($payload) + 2).$payload;

        return NameplatePhoto::of(substr($jpeg, 0, 2).$segment.substr($jpeg, 2), 'image/jpeg');
    }
}
