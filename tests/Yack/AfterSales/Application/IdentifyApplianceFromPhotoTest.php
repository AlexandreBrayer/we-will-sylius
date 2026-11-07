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
use App\Yack\Common\Domain\AiUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Hand-written fakes of the two ports (reader, installed base): runs without vendor/.
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

    #[\Override]
    protected function setUp(): void
    {
        $this->photo = NameplatePhoto::of('photo', 'image/png');
        $this->lookups = [];
    }

    public function testKnownSerialIsFoundAfterNormalisation(): void
    {
        $result = $this->identify(NameplateReading::read('yk24-hyd11s 000321', 'Hydra Split 11 kW', simulated: true));

        $this->assertSame(IdentificationStatus::Found, $result->status);
        $this->assertSame(self::KNOWN, $result->serialNumber);
        $this->assertSame('Hydra Split 11 kW', $result->model);
        $this->assertTrue($result->simulated);
        $this->assertNull($result->message());
        $this->assertSame([self::KNOWN], $this->lookups);
    }

    public function testSerialAbsentFromTheInstalledBaseIsUnknown(): void
    {
        $result = $this->identify(NameplateReading::read('YK23EVO06M009999', null, simulated: false));

        $this->assertSame(IdentificationStatus::Unknown, $result->status);
        $this->assertSame('YK23EVO06M009999', $result->serialNumber);
        $this->assertFalse($result->simulated);
        $this->assertSame('Appareil non trouvé', $result->message());
    }

    public function testUnreadablePlateIsNotLookedUp(): void
    {
        $result = $this->identify(NameplateReading::unreadable(simulated: true));

        $this->assertSame(IdentificationStatus::Unreadable, $result->status);
        $this->assertNull($result->serialNumber);
        $this->assertSame('Plaque illisible : saisissez le numéro de série', $result->message());
        $this->assertSame([], $this->lookups);
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

        (new IdentifyApplianceFromPhoto($reader, $this->catalog()))($this->photo);

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

        return (new IdentifyApplianceFromPhoto($reader, $this->catalog()))($this->photo);
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
        };
    }
}
