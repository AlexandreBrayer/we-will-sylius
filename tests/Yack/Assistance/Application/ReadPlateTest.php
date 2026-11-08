<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Application;

use App\Yack\AfterSales\Application\ReadNameplate;
use App\Yack\AfterSales\Application\ReadNameplateInterface;
use App\Yack\AfterSales\Domain\NameplatePhotoRefused;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\AfterSales\Infrastructure\Nameplate\FakeNameplateReader;
use App\Yack\Assistance\Application\PlateReading;
use App\Yack\Assistance\Application\ReadPlate;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lecture d'AfterSales écrite à la main (elle rend la lecture qu'on lui donne et retient les photos reçues), puis
 * la vraie chaîne de démonstration (ReadNameplate et FakeNameplateReader) sur les plaques FICTIF : tourne sans
 * vendor/. ReadPlate ne reçoit que la lecture : il ne peut chercher aucun document.
 *
 * @internal
 */
#[CoversClass(ReadPlate::class)]
#[CoversClass(PlateReading::class)]
final class ReadPlateTest extends TestCase
{
    /** @var list<string> */
    private array $photos = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->photos = [];
    }

    public function testTheBrandPrintedOnThePlateIsKept(): void
    {
        $plate = $this->readPlate(NameplateReading::read('MH21ZSW0004242', 'Mural 2,5 kW R32', simulated: true, reference: 'SRK25ZS-W', brand: 'MHI'));

        $this->assertSame('MH21ZSW0004242', $plate->serialNumber);
        $this->assertSame('SRK25ZS-W', $plate->referenceRead);
        $this->assertSame('SRK25ZS-W', $plate->reference);
        $this->assertSame('Mural 2,5 kW R32', $plate->model);
        $this->assertSame('MHI', $plate->brand);
        $this->assertSame('plaque', $plate->brandSource);
        $this->assertTrue($plate->simulated);
        $this->assertFalse($plate->isEmpty());
        $this->assertSame(['photo'], $this->photos);
    }

    public function testWithoutABrandOnThePlateItIsDeducedFromTheReference(): void
    {
        $plate = $this->readPlate(NameplateReading::read(null, null, simulated: true, reference: 'SRK25ZS-W'));

        $this->assertSame('MHI', $plate->brand);
        $this->assertSame('deduite', $plate->brandSource);
    }

    public function testThePrintedBrandWinsOverTheDeduction(): void
    {
        $plate = $this->readPlate(NameplateReading::read('YK19KAZ35S004567', null, simulated: true, reference: 'SRK25ZS-WFT', brand: 'YACK'));

        $this->assertSame('YACK', $plate->brand);
        $this->assertSame('plaque', $plate->brandSource);
    }

    public function testAMisreadReferenceIsUnderstoodAndTheReadingKept(): void
    {
        $plate = $this->readPlate(NameplateReading::read(null, null, simulated: false, reference: 'srk2szs w'));

        $this->assertSame('srk2szs w', $plate->referenceRead);
        $this->assertSame('SRK25ZS-W', $plate->reference);
        $this->assertSame('MHI', $plate->brand);
        $this->assertSame('deduite', $plate->brandSource);
        $this->assertFalse($plate->simulated);
    }

    public function testAReferenceOfNoKnownBrandHasNoBrand(): void
    {
        $plate = $this->readPlate(NameplateReading::read('YK23EVO06M009999', null, simulated: true, reference: 'YK-PAE-M06'));

        $this->assertSame('YK-PAE-M06', $plate->reference);
        $this->assertNull($plate->brand);
        $this->assertNull($plate->brandSource);
    }

    public function testAReadingThatIsOnlyALabelGivesNoReference(): void
    {
        $plate = $this->readPlate(NameplateReading::read(null, null, simulated: true, reference: 'Model :'));

        $this->assertSame('Model :', $plate->referenceRead);
        $this->assertNull($plate->reference);
        $this->assertNull($plate->brand);
        $this->assertTrue($plate->isEmpty());
    }

    public function testAnUnreadablePlateGivesEmptyFields(): void
    {
        $plate = $this->readPlate(NameplateReading::unreadable(simulated: true));

        $this->assertNull($plate->serialNumber);
        $this->assertNull($plate->referenceRead);
        $this->assertNull($plate->reference);
        $this->assertNull($plate->model);
        $this->assertNull($plate->brand);
        $this->assertNull($plate->brandSource);
        $this->assertTrue($plate->isEmpty());
    }

    public function testAnUnreadablePlateKeepsAPrintedBrand(): void
    {
        $plate = $this->readPlate(NameplateReading::unreadable(simulated: false, brand: 'MHI'));

        $this->assertTrue($plate->isEmpty());
        $this->assertSame('MHI', $plate->brand);
        $this->assertSame('plaque', $plate->brandSource);
    }

    public function testAnUnavailableReadingGoesUp(): void
    {
        $this->expectException(AiUnavailable::class);
        $this->readPlate(AiUnavailable::because('plafond atteint'));
    }

    public function testARefusedPhotoGoesUp(): void
    {
        $this->expectException(NameplatePhotoRefused::class);
        $this->readPlate(NameplatePhotoRefused::format());
    }

    /**
     * Chaîne de démonstration complète (YACK_AI_ADAPTER=fake) sur les plaques FICTIF.
     *
     * @param array{?string, ?string, ?string, ?string, ?string, ?string} $expected
     */
    #[DataProvider('demoPlates')]
    public function testDemoPlates(string $name, array $expected): void
    {
        $file = \dirname(__DIR__, 4).'/public/yack/demo/plaques/plaque-'.$name.'-FICTIF.png';
        $this->assertTrue(is_file($file), $file);

        $plate = (new ReadPlate(new ReadNameplate(new FakeNameplateReader())))((string) file_get_contents($file));

        $this->assertSame($expected, [$plate->serialNumber, $plate->referenceRead, $plate->reference, $plate->model, $plate->brand, $plate->brandSource]);
        $this->assertTrue($plate->simulated);
    }

    /** @return \Iterator<string, array{string, array{?string, ?string, ?string, ?string, ?string, ?string}}> */
    public static function demoPlates(): \Iterator
    {
        yield 'MHI label: brand, catalog reference and serial read' => ['SRK25ZS-W', ['MH21ZSW0004242', 'SRK25ZS-W', 'SRK25ZS-W', 'Mural 2,5 kW R32', 'MHI', 'plaque']];
        yield 'label without a printed brand: brand deduced' => ['sans-marque', ['MH22ZSW0003535', 'SRK35ZS-W', 'SRK35ZS-W', 'Mural 3,5 kW R32', 'MHI', 'deduite']];
        yield 'blurred photo: every field empty' => ['illisible', [null, null, null, null, null, null]];
        yield 'Yack plate of the installed base' => ['YK19KAZ35S004567', ['YK19KAZ35S004567', 'SRK25ZS-WFT', 'SRK25ZS-WFT', 'Mural Titane Wifi 2,5 kW', 'YACK', 'plaque']];
    }

    private function readPlate(NameplateReading|AiUnavailable|NameplatePhotoRefused $answer): PlateReading
    {
        $nameplate = new class($answer, $this->photos) implements ReadNameplateInterface {
            /** @param list<string> $photos */
            public function __construct(private NameplateReading|AiUnavailable|NameplatePhotoRefused $answer, private array &$photos)
            {
            }

            public function read(string $bytes): NameplateReading
            {
                $this->photos[] = $bytes;
                if (!$this->answer instanceof NameplateReading) {
                    throw $this->answer;
                }

                return $this->answer;
            }
        };

        return (new ReadPlate($nameplate))('photo');
    }
}
