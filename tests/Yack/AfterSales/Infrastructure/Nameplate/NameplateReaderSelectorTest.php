<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Infrastructure\Nameplate;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use App\Yack\AfterSales\Infrastructure\Nameplate\LanguageModelNameplateReader;
use App\Yack\AfterSales\Infrastructure\Nameplate\NameplateReaderSelector;
use App\Yack\Common\Domain\AiMisconfigured;
use App\Yack\Common\Domain\AiUnavailable;
use App\Yack\Common\Domain\LanguageModel;
use App\Yack\Common\Infrastructure\Image\ExifStripper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 */
#[CoversClass(NameplateReaderSelector::class)]
final class NameplateReaderSelectorTest extends TestCase
{
    /** What the marker of plaque-SRK25ZS-W-FICTIF.png carries. */
    private const DEMO_SERIAL = 'MH21ZSW0004242';

    private FakeLanguageModel $languageModel;

    private NameplatePhoto $photo;

    private NameplateSelectorRecordingLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->languageModel = new FakeLanguageModel(['readable' => true, 'serial' => 'YK25EVO08M001234', 'reference' => null, 'model' => 'PAC air/eau RHOSS R32']);
        $this->photo = NameplatePhoto::of(self::plainPng(), 'image/png');
        $this->logger = new NameplateSelectorRecordingLogger();
    }

    #[DataProvider('demoModes')]
    public function testDemoReaderByDefault(?string $mode): void
    {
        $reading = $this->selector($mode)->read($this->photo);

        $this->assertTrue($reading->simulated);
        $this->assertSame([], $this->languageModel->calls);
    }

    /** @return \Iterator<string, array{?string}> */
    public static function demoModes(): \Iterator
    {
        yield 'unset' => [null];
        yield 'empty' => [''];
        yield 'fake' => ['fake'];
        yield 'unknown value' => ['openai'];
    }

    #[DataProvider('realModes')]
    public function testRealReaderWhenAProviderIsNamed(string $mode): void
    {
        $reading = $this->selector($mode)->read($this->photo);

        $this->assertFalse($reading->simulated);
        $this->assertSame('YK25EVO08M001234', $reading->serialNumber);
        $this->assertCount(1, $this->languageModel->calls);
    }

    /** The real answer wins, even for a FICTIF plate: the fallback only plays when the model cannot answer. */
    #[DataProvider('realModes')]
    public function testRealAnswerWinsOnADemoPlate(string $mode): void
    {
        $reading = $this->selector($mode)->read(self::demoPlate('SRK25ZS-W'));

        $this->assertFalse($reading->simulated);
        $this->assertSame('YK25EVO08M001234', $reading->serialNumber);
        $this->assertCount(1, $this->languageModel->calls);
        $this->assertSame([], $this->logger->records);
    }

    /** @return \Iterator<string, array{string}> */
    public static function realModes(): \Iterator
    {
        yield 'anthropic' => [' Anthropic '];
        yield 'mistral' => ['mistral'];
    }

    #[DataProvider('unavailableReasons')]
    public function testDemoPlateIsReadByTheDemoReaderWhenTheModelIsUnavailable(string $reason): void
    {
        $this->languageModel = new FakeLanguageModel(AiUnavailable::because($reason));

        $reading = $this->selector('anthropic')->read(self::demoPlate('SRK25ZS-W'));

        $this->assertTrue($reading->simulated);
        $this->assertSame(self::DEMO_SERIAL, $reading->serialNumber);
        $this->assertSame('SRK25ZS-W', $reading->reference);
        $this->assertSame('MHI', $reading->brand);
        $this->assertCount(1, $this->languageModel->calls, 'Le repli ne fait aucun autre appel.');

        $this->assertCount(1, $this->logger->records);
        [$level, $message, $context] = $this->logger->records[0];
        $this->assertSame('warning', $level);
        $this->assertSame(NameplateReaderSelector::FALLBACK_LOG, $message);
        $this->assertSame(['purpose' => LanguageModelNameplateReader::PURPOSE, 'reason' => 'Service IA indisponible : ' . $reason, 'reader' => 'demo'], $context);
        $logged = $message . json_encode($context, \JSON_THROW_ON_ERROR);
        foreach ([self::DEMO_SERIAL, 'SRK25ZS-W', 'MHI', 'Mural', 'iVBOR'] as $printed) {
            $this->assertStringNotContainsString($printed, $logged, 'Aucune donnée de la photo dans le journal.');
        }
    }

    /** @return \Iterator<string, array{string}> */
    public static function unavailableReasons(): \Iterator
    {
        yield 'HTTP error' => ['réponse 400'];
        yield 'timeout' => ['délai dépassé ou réseau'];
        yield 'daily cap' => ['plafond quotidien atteint'];
        yield 'no structured content' => ['réponse sans contenu structuré'];
    }

    public function testEveryDemoPlateWithAMarkerFallsBack(): void
    {
        $this->languageModel = new FakeLanguageModel(AiUnavailable::because('réponse 503'));

        foreach (['YK25EVO08M001234', 'YK19KAZ35S004567', 'YK24HYD11S000321', 'YK22THD200000789', 'YK23EVO06M009999', 'sans-marque'] as $plate) {
            $reading = $this->selector('mistral')->read(self::demoPlate($plate));

            $this->assertTrue($reading->simulated, $plate);
            $this->assertTrue($reading->isReadable(), $plate);
        }
    }

    /** A real photo (no marker) keeps the current behaviour: AiUnavailable, so 503 « Lecture indisponible ». */
    #[DataProvider('photosWithoutMarker')]
    public function testNoFallbackForAPhotoWithoutTheDemoMarker(\Closure $photo): void
    {
        $unavailable = AiUnavailable::because('réponse 400');
        $this->languageModel = new FakeLanguageModel($unavailable);

        try {
            $this->selector('anthropic')->read($photo());
            $this->fail('AiUnavailable attendue.');
        } catch (AiUnavailable $raised) {
            $this->assertSame($unavailable, $raised);
        }
        $this->assertCount(1, $this->languageModel->calls);
        $this->assertSame([], $this->logger->records);
    }

    /** @return \Iterator<string, array{\Closure(): NameplatePhoto}> */
    public static function photosWithoutMarker(): \Iterator
    {
        yield 'PNG' => [static fn (): NameplatePhoto => NameplatePhoto::of(self::plainPng(), 'image/png')];
        yield 'JPEG' => [static function (): NameplatePhoto {
            $image = imagecreatetruecolor(10, 10);
            ob_start();
            imagejpeg($image);

            return NameplatePhoto::of((string) ob_get_clean(), 'image/jpeg');
        }];
        yield 'FICTIF blurred plate, no marker' => [static fn (): NameplatePhoto => self::demoPlate('illisible')];
    }

    /**
     * Only the shipped files fall back: a client photo carrying a forged marker (here a serial of the installed
     * base), or a demo plate changed by one byte, keeps AiUnavailable, so 503 « Lecture indisponible ».
     */
    #[DataProvider('forgedDemoPlates')]
    public function testNoFallbackForAPhotoThatIsNotAShippedDemoPlate(\Closure $photo): void
    {
        $this->languageModel = new FakeLanguageModel(AiUnavailable::because('réponse 400'));
        $forged = $photo();
        $this->assertNotNull((new FakeNameplateReader())->readDemoPlate($forged), 'Le bloc texte est lisible : seul le contrôle d\'empreinte l\'écarte.');

        try {
            $this->selector('anthropic')->read($forged);
            $this->fail('AiUnavailable attendue.');
        } catch (AiUnavailable) {
        }
        $this->assertCount(1, $this->languageModel->calls);
        $this->assertSame([], $this->logger->records);
    }

    /** @return \Iterator<string, array{\Closure(): NameplatePhoto}> */
    public static function forgedDemoPlates(): \Iterator
    {
        yield 'client PNG with a forged marker' => [static function (): NameplatePhoto {
            $data = FakeNameplateReader::MARKER . "\0" . json_encode(['serial' => 'YK24HYD11S000321', 'reference' => 'FDCW-VNX-A', 'model' => 'Texte du client', 'brand' => 'CLIENT'], \JSON_THROW_ON_ERROR);
            $png = self::plainPng();

            return NameplatePhoto::of(substr($png, 0, 33) . pack('N', \strlen($data)) . 'tEXt' . $data . pack('N', crc32('tEXt' . $data)) . substr($png, 33), 'image/png');
        }];
        yield 'demo plate with one byte added' => [static fn (): NameplatePhoto => NameplatePhoto::of(self::demoPlate('SRK25ZS-W')->bytes . "\0", 'image/png')];
    }

    /** The directory decides: without the shipped files, a genuine demo plate does not fall back either. */
    public function testNoFallbackWhenTheDemoPlatesDirectoryHasNotThePhoto(): void
    {
        $this->languageModel = new FakeLanguageModel(AiUnavailable::because('réponse 400'));
        $selector = new NameplateReaderSelector(new FakeNameplateReader(), new LanguageModelNameplateReader($this->languageModel, new ExifStripper()), 'anthropic', $this->logger, sys_get_temp_dir() . '/yack-aucune-plaque-' . bin2hex(random_bytes(4)));

        $this->expectException(AiUnavailable::class);
        $selector->read(self::demoPlate('SRK25ZS-W'));
    }

    /** A configuration error (key or model missing) stays an error, even for a FICTIF plate. */
    public function testNoFallbackWhenTheProviderIsMisconfigured(): void
    {
        $languageModel = $this->createMock(LanguageModel::class);
        $languageModel->expects($this->once())->method('extract')->willThrowException(AiMisconfigured::missing('ANTHROPIC_API_KEY'));
        $selector = new NameplateReaderSelector(new FakeNameplateReader(), new LanguageModelNameplateReader($languageModel, new ExifStripper()), 'anthropic', $this->logger);

        $this->expectException(AiMisconfigured::class);

        try {
            $selector->read(self::demoPlate('SRK25ZS-W'));
        } finally {
            $this->assertSame([], $this->logger->records);
        }
    }

    private function selector(?string $mode): NameplateReaderSelector
    {
        return new NameplateReaderSelector(
            new FakeNameplateReader(),
            new LanguageModelNameplateReader($this->languageModel, new ExifStripper()),
            $mode,
            $this->logger,
        );
    }

    private static function demoPlate(string $name): NameplatePhoto
    {
        $file = \dirname(__DIR__, 5) . '/public/yack/demo/plaques/plaque-' . $name . '-FICTIF.png';
        self::assertTrue(is_file($file), $file);

        return NameplatePhoto::of((string) file_get_contents($file), 'image/png');
    }

    private static function plainPng(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}

/**
 * Keeps the records, to check the level and that nothing read on the photo is logged.
 *
 * @internal
 */
final class NameplateSelectorRecordingLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<mixed>}> */
    public array $records = [];

    /** @param array<mixed> $context */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [\is_string($level) ? $level : '', (string) $message, $context];
    }
}
