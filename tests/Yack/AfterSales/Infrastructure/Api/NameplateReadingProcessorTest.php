<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\AfterSales\Model;
use App\Yack\AfterSales\Application\IdentifyApplianceFromPhoto;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\AfterSales\Infrastructure\Api\NameplateReadingProcessor;
use App\Yack\AfterSales\Infrastructure\Api\NameplateReadingView;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Needs Symfony and API Platform (vendor/): runs on the Mac only.
 *
 * @internal
 */
#[CoversClass(NameplateReadingProcessor::class)]
#[CoversClass(NameplateReadingView::class)]
final class NameplateReadingProcessorTest extends TestCase
{
    private const PLATES = __DIR__.'/../../../../../public/yack/demo/plaques';

    public function testDemoPlateOfAKnownApplianceIsFound(): void
    {
        $upload = self::upload(self::PLATES.'/plaque-YK24HYD11S000321-FICTIF.png', 'plaque.png');

        $view = $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => self::request($upload)]);

        $this->assertSame('found', $view->status);
        $this->assertSame('YK24HYD11S000321', $view->serialNumber);
        $this->assertSame('Hydra Split 11 kW', $view->model);
        $this->assertTrue($view->simulated);
    }

    public function testDemoPlateOutsideTheInstalledBaseIsUnknown(): void
    {
        $upload = self::upload(self::PLATES.'/plaque-YK23EVO06M009999-FICTIF.png', 'plaque.png');

        $view = $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => self::request($upload)]);

        $this->assertSame('unknown', $view->status);
        $this->assertSame('YK23EVO06M009999', $view->serialNumber);
    }

    public function testThePhotoIsNotKept(): void
    {
        $upload = self::upload(self::PLATES.'/plaque-YK24HYD11S000321-FICTIF.png', 'plaque.png');
        $path = $upload->getPathname();

        $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => self::request($upload)]);

        $this->assertFalse(is_file($path));
    }

    public function testTypeIsReadOnTheContentNotOnTheName(): void
    {
        $upload = self::uploadOf('%PDF-1.7 pas une photo', 'plaque.png');

        try {
            $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => self::request($upload)]);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $exception) {
            $violation = $exception->getConstraintViolationList()->get(0);
            $this->assertSame('photo', $violation->getPropertyPath());
            $this->assertSame('Format non accepté (JPG, JPEG, PNG).', $violation->getMessage());
        }
        $this->assertFalse(is_file($upload->getPathname()));
    }

    public function testMissingPhotoIsRefused(): void
    {
        $this->expectException(ValidationException::class);
        $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => new Request()]);
    }

    public function testUnavailableReadingIsA503WithAClearMessage(): void
    {
        $reader = new class implements NameplateReader {
            public function read(NameplatePhoto $photo): NameplateReading
            {
                throw AiUnavailable::because('trop de demandes');
            }
        };
        $upload = self::upload(self::PLATES.'/plaque-YK24HYD11S000321-FICTIF.png', 'plaque.png');

        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage('Lecture indisponible : saisissez le numéro de série');
        $this->processor($reader)->process(null, new Post(), [], ['request' => self::request($upload)]);
    }

    private function processor(NameplateReader $reader): NameplateReadingProcessor
    {
        $catalog = $this->createStub(AfterSalesCatalog::class);
        $catalog->method('findModels')->willReturnCallback(
            fn (string $serial): array => 'YK24HYD11S000321' === $serial ? [new Model('SC-HYD-O11', 'Hydra Split 11 kW', 'PAC air/eau split')] : [],
        );

        return new NameplateReadingProcessor(new IdentifyApplianceFromPhoto($reader, $catalog));
    }

    private static function request(UploadedFile $upload): Request
    {
        return new Request(files: ['photo' => $upload]);
    }

    /** Copy of a file as a test upload (UploadedFile in test mode: no is_uploaded_file() check). */
    private static function upload(string $source, string $name): UploadedFile
    {
        return self::uploadOf((string) file_get_contents($source), $name);
    }

    private static function uploadOf(string $bytes, string $name): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'yack-plate-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }
}
