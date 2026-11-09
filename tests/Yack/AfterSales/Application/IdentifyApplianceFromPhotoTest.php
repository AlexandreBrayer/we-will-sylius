<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Application;

use App\Entity\AfterSales\ExplodedView;
use App\Entity\AfterSales\Model;
use App\Yack\AfterSales\Application\ApplianceIdentification;
use App\Yack\AfterSales\Application\IdentificationStatus;
use App\Yack\AfterSales\Application\IdentifyApplianceFromPhoto;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;
use App\Yack\Catalog\Application\ProductReferenceReaderInterface;
use App\Yack\Catalog\Domain\ProductReference;
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Hand-written fakes of the reader, the installed base and the catalog: runs without vendor/.
 *
 * @internal
 */
#[CoversClass(IdentifyApplianceFromPhoto::class)]
final class IdentifyApplianceFromPhotoTest extends TestCase
{
    private const KNOWN = 'YK24HYD11S000321';

    private NameplatePhoto $photo;

    /** @var list<string> */
    private array $lookups = [];

    /** @var list<string> */
    private array $productLookups = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->photo = NameplatePhoto::of('photo', 'image/png');
        $this->lookups = [];
        $this->productLookups = [];
    }

    public function testKnownSerialIsFoundAfterNormalisation(): void
    {
        $result = $this->identify(NameplateReading::read('yk24-hyd11s 000321', 'Hydrolution', simulated: true, reference: 'FDCW-VNX-A'));

        $this->assertSame(IdentificationStatus::Found, $result->status);
        $this->assertSame(self::KNOWN, $result->serialNumber);
        $this->assertSame('Hydrolution', $result->model);
        $this->assertSame('FDCW-VNX-A', $result->reference);
        $this->assertNull($result->product);
        $this->assertTrue($result->simulated);
        $this->assertNull($result->message());
        $this->assertSame([self::KNOWN], $this->lookups);
        $this->assertSame([], $this->productLookups);
    }

    public function testCatalogReferenceIsProposedWithoutAKnownSerial(): void
    {
        $result = $this->identify(NameplateReading::read('MH21ZSW0004242', 'Mural 2,5 kW', simulated: true, reference: 'SRK25ZS-W'));

        $this->assertSame(IdentificationStatus::Product, $result->status);
        $this->assertSame('SRK25ZS-W', $result->product?->code);
        $this->assertSame('srk25zs-w-mural', $result->product?->slug);
        $this->assertSame('MH21ZSW0004242', $result->serialNumber);
        $this->assertSame('Modèle reconnu dans le catalogue', $result->message());
        $this->assertSame(['MH21ZSW0004242'], $this->lookups);
        $this->assertSame(['SRK25ZS-W'], $this->productLookups);
    }

    public function testReferenceAloneIsLookedUpInTheCatalogOnly(): void
    {
        $result = $this->identify(NameplateReading::read(null, null, simulated: false, reference: 'srk25zs-w'));

        $this->assertSame(IdentificationStatus::Product, $result->status);
        $this->assertNull($result->serialNumber);
        $this->assertSame([], $this->lookups);
    }

    public function testModelNameIsTriedWhenNothingElseWasRead(): void
    {
        $result = $this->identify(NameplateReading::unreadable(simulated: false, model: 'SRK25ZS-W'));

        $this->assertSame(IdentificationStatus::Product, $result->status);
        $this->assertNull($result->reference);
        $this->assertSame('SRK25ZS-W', $result->model);
    }

    public function testSerialAbsentFromTheInstalledBaseAndTheCatalogIsUnknown(): void
    {
        $result = $this->identify(NameplateReading::read('YK23EVO06M009999', 'Evolys monobloc 6 kW', simulated: false, reference: 'YK-PAE-M06'));

        $this->assertSame(IdentificationStatus::Unknown, $result->status);
        $this->assertSame('YK23EVO06M009999', $result->serialNumber);
        $this->assertSame('YK-PAE-M06', $result->reference);
        $this->assertNull($result->product);
        $this->assertFalse($result->simulated);
        $this->assertSame('Appareil non trouvé', $result->message());
        $this->assertSame(['YK-PAE-M06', 'Evolys monobloc 6 kW', 'YK23EVO06M009999'], $this->productLookups);
    }

    public function testUnreadablePlateIsNotLookedUp(): void
    {
        $result = $this->identify(NameplateReading::unreadable(simulated: true));

        $this->assertSame(IdentificationStatus::Unreadable, $result->status);
        $this->assertNull($result->serialNumber);
        $this->assertSame('Plaque illisible : saisissez le numéro de série', $result->message());
        $this->assertSame([], $this->lookups);
        $this->assertSame([], $this->productLookups);
    }

    public function testUnavailableReadingServiceGivesAClearMessage(): void
    {
        $result = $this->identify(AiUnavailable::because('délai dépassé ou réseau'));

        $this->assertSame(IdentificationStatus::Unavailable, $result->status);
        $this->assertSame(ApplianceIdentification::UNAVAILABLE_MESSAGE, $result->message());
        $this->assertSame('Lecture indisponible : saisissez le numéro de série', $result->message());
        $this->assertSame([], $this->lookups);
    }

    public function testThePhotoIsPassedToTheReader(): void
    {
        $reader = new class implements NameplateReader {
            public ?NameplatePhoto $seen = null;

            public function read(NameplatePhoto $photo): NameplateReading
            {
                $this->seen = $photo;

                return NameplateReading::unreadable(simulated: true);
            }
        };

        (new IdentifyApplianceFromPhoto($reader, $this->catalog(), $this->products()))($this->photo);

        $this->assertSame($this->photo, $reader->seen);
    }

    private function identify(NameplateReading|AiUnavailable $answer): ApplianceIdentification
    {
        $reader = new class($answer) implements NameplateReader {
            public function __construct(private NameplateReading|AiUnavailable $answer)
            {
            }

            public function read(NameplatePhoto $photo): NameplateReading
            {
                if ($this->answer instanceof AiUnavailable) {
                    throw $this->answer;
                }

                return $this->answer;
            }
        };

        return (new IdentifyApplianceFromPhoto($reader, $this->catalog(), $this->products()))($this->photo);
    }

    /** Catalog knowing only the reference SRK25ZS-W (any case); records the texts looked up. */
    private function products(): ProductReferenceReaderInterface
    {
        $lookups = &$this->productLookups;

        return new class($lookups) implements ProductReferenceReaderInterface {
            /** @param list<string> $lookups */
            public function __construct(private array &$lookups)
            {
            }

            public function find(string $printedReference): ?ProductReference
            {
                $this->lookups[] = $printedReference;

                return 'SRK25ZS-W' === strtoupper($printedReference)
                    ? new ProductReference('SRK25ZS-W', 'SRK25ZS-W Mural - 2,5kW - R32', 'srk25zs-w-mural', 'MHI', ['Notice diagnostic'])
                    : null;
            }
        };
    }

    /** Installed base knowing only KNOWN; records the serials looked up. */
    private function catalog(): AfterSalesCatalog
    {
        $lookups = &$this->lookups;

        return new class($lookups) implements AfterSalesCatalog {
            /** @param list<string> $lookups */
            public function __construct(private array &$lookups)
            {
            }

            public function findModels(string $serialOrServiceCode): array
            {
                $this->lookups[] = $serialOrServiceCode;

                // A Model without its Doctrine collections: only the count matters here.
                return 'YK24HYD11S000321' === $serialOrServiceCode
                    ? [(new \ReflectionClass(Model::class))->newInstanceWithoutConstructor()]
                    : [];
            }

            public function findModelByServiceCode(string $serviceCode): ?Model
            {
                return null;
            }

            public function findExplodedView(Model $model, int $explodedViewId): ?ExplodedView
            {
                return null;
            }

            public function findExplodedViewById(int $explodedViewId): ?ExplodedView
            {
                return null;
            }

            public function installedBase(): array
            {
                return [];
            }
        };
    }
}
