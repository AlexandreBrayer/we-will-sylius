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
use App\Yack\Catalog\Application\ProductReferenceReaderInterface;
use App\Yack\Catalog\Domain\ProductReference;
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
        $this->assertSame('FDCW-VNX-A', $view->reference);
        $this->assertSame('Hydrolution', $view->model);
        $this->assertNull($view->product);
        $this->assertTrue($view->simulated);
    }

    public function testDemoLabelOfACatalogReferenceProposesTheProduct(): void
    {
        $upload = self::upload(self::PLATES.'/plaque-SRK25ZS-W-FICTIF.png', 'IMG_0001.png');

        $view = $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => self::request($upload)]);

        $this->assertSame('product', $view->status);
        $this->assertSame('MH21ZSW0004242', $view->serialNumber);
        $this->assertSame('SRK25ZS-W', $view->reference);
        $this->assertSame([
            'code' => 'SRK25ZS-W',
            'name' => 'SRK25ZS-W Mural - 2,5kW - R32',
            'slug' => 'srk25zs-w-mural-2-5kw-r32-srk25zs-w',
            'brand' => 'MHI',
            'documents' => ['Documentation Produit', 'Notice diagnostic'],
        ], $view->product);
    }

    public function testBlurredDemoPhotoIsUnreadable(): void
    {
        $upload = self::upload(self::PLATES.'/plaque-illisible-FICTIF.png', 'plaque.png');

        $view = $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => self::request($upload)]);

        $this->assertSame('unreadable', $view->status);
        $this->assertNull($view->serialNumber);
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
            $this->assertSame('Format non accepté (JPEG, PNG ou WebP).', $violation->getMessage());
        }
        $this->assertFalse(is_file($upload->getPathname()));
    }

    public function testHeicArrivingAsIsHasItsOwnMessage(): void
    {
        // Start of an ISO-BMFF HEIC file: box `ftyp`, brand `heic` (finfo reads image/heic).
        $upload = self::uploadOf("\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64), 'IMG_0001.HEIC');

        try {
            $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => self::request($upload)]);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $exception) {
            $this->assertSame('Photo HEIC non lue : envoyez-la en JPEG, PNG ou WebP.', $exception->getConstraintViolationList()->get(0)->getMessage());
        }
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
            fn (string $serial): array => 'YK24HYD11S000321' === $serial ? [new Model('SC-HYD-O11', 'Hydrolution : groupe extérieur', 'PAC air/eau split')] : [],
        );
        $products = $this->createStub(ProductReferenceReaderInterface::class);
        $products->method('find')->willReturnCallback(
            fn (string $reference): ?ProductReference => 'SRK25ZS-W' === $reference
                ? new ProductReference('SRK25ZS-W', 'SRK25ZS-W Mural - 2,5kW - R32', 'srk25zs-w-mural-2-5kw-r32-srk25zs-w', 'MHI', ['Documentation Produit', 'Notice diagnostic'])
                : null,
        );

        return new NameplateReadingProcessor(new IdentifyApplianceFromPhoto($reader, $catalog, $products));
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
