<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\AfterSales\Application\ReadNameplate;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use App\Yack\Assistance\Application\ReadPlate;
use App\Yack\Assistance\Infrastructure\Api\AssistanceSwitch;
use App\Yack\Assistance\Infrastructure\Api\PlateReadingProcessor;
use App\Yack\Assistance\Infrastructure\Api\PlateReadingView;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Ce test a besoin de Symfony et d'API Platform (vendor/) : PHPUnit seulement. Lecture par l'adapter de
 * démonstration sur les plaques FICTIF, ou par un lecteur écrit à la main.
 *
 * @internal
 */
#[CoversClass(PlateReadingProcessor::class)]
#[CoversClass(PlateReadingView::class)]
final class PlateReadingProcessorTest extends TestCase
{
    private const PLATES = __DIR__.'/../../../../../public/yack/demo/plaques';

    public function testTheMhiDemoLabelIsReadWithoutAnyLookup(): void
    {
        $view = $this->process(self::upload(self::PLATES.'/plaque-SRK25ZS-W-FICTIF.png', 'IMG_0001.png'));

        $this->assertSame('MH21ZSW0004242', $view->serialNumber);
        $this->assertSame('SRK25ZS-W', $view->referenceRead);
        $this->assertSame('SRK25ZS-W', $view->reference);
        $this->assertSame('Mural 2,5 kW R32', $view->model);
        $this->assertSame('MHI', $view->brand);
        $this->assertSame('plaque', $view->brandSource);
        $this->assertTrue($view->simulated);
    }

    public function testALabelWithoutPrintedBrandGivesTheDeducedBrand(): void
    {
        $view = $this->process(self::upload(self::PLATES.'/plaque-sans-marque-FICTIF.png', 'plaque.png'));

        $this->assertSame('SRK35ZS-W', $view->reference);
        $this->assertSame('MHI', $view->brand);
        $this->assertSame('deduite', $view->brandSource);
    }

    public function testTheBlurredDemoPhotoGivesEmptyFields(): void
    {
        $view = $this->process(self::upload(self::PLATES.'/plaque-illisible-FICTIF.png', 'plaque.png'));

        $this->assertSame([null, null, null, null, null, null], [$view->serialNumber, $view->referenceRead, $view->reference, $view->model, $view->brand, $view->brandSource]);
        $this->assertTrue($view->simulated);
    }

    public function testThePhotoIsNotKept(): void
    {
        $upload = self::upload(self::PLATES.'/plaque-SRK25ZS-W-FICTIF.png', 'plaque.png');

        $this->process($upload);

        $this->assertFalse(is_file($upload->getPathname()));
    }

    public function testAPdfNamedPngIsRefusedOnThePhotoField(): void
    {
        $upload = self::uploadOf("%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n", 'plaque.png');

        try {
            $this->process($upload);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $exception) {
            $violation = $exception->getConstraintViolationList()->get(0);
            $this->assertSame('photo', $violation->getPropertyPath());
            $this->assertSame('Format non accepté (JPEG, PNG ou WebP).', $violation->getMessage());
        }
        $this->assertFalse(is_file($upload->getPathname()));
    }

    public function testAMissingPhotoIsRefused(): void
    {
        try {
            $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => new Request()]);
            $this->fail('ValidationException attendue.');
        } catch (ValidationException $exception) {
            $violation = $exception->getConstraintViolationList()->get(0);
            $this->assertSame('photo', $violation->getPropertyPath());
            $this->assertSame('Photo obligatoire.', $violation->getMessage());
        }
    }

    public function testAnUnavailableReadingIsA503WithAClearMessage(): void
    {
        $reader = new class implements NameplateReader {
            public function read(NameplatePhoto $photo): NameplateReading
            {
                throw AiUnavailable::because('plafond atteint');
            }
        };

        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage('Lecture indisponible : saisissez la référence');
        $this->processor($reader)->process(null, new Post(), [], ['request' => self::request(self::upload(self::PLATES.'/plaque-SRK25ZS-W-FICTIF.png', 'plaque.png'))]);
    }

    public function testADisabledModuleAnswersNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->processor(new FakeNameplateReader(), false)->process(null, new Post(), [], ['request' => self::request(self::upload(self::PLATES.'/plaque-SRK25ZS-W-FICTIF.png', 'plaque.png'))]);
    }

    private function process(UploadedFile $upload): PlateReadingView
    {
        return $this->processor(new FakeNameplateReader())->process(null, new Post(), [], ['request' => self::request($upload)]);
    }

    private function processor(NameplateReader $reader, bool $enabled = true): PlateReadingProcessor
    {
        return new PlateReadingProcessor(new AssistanceSwitch($enabled), new ReadPlate(new ReadNameplate($reader)));
    }

    private static function request(UploadedFile $upload): Request
    {
        return new Request(files: ['photo' => $upload]);
    }

    /** Copie d'un fichier en téléversement de test (UploadedFile en mode test : pas de contrôle is_uploaded_file()). */
    private static function upload(string $source, string $name): UploadedFile
    {
        return self::uploadOf((string) file_get_contents($source), $name);
    }

    private static function uploadOf(string $bytes, string $name): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'yack-assistance-plate-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }
}
