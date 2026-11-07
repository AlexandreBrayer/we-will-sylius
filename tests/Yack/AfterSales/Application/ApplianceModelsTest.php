<?php

declare(strict_types=1);

namespace App\Tests\Yack\AfterSales\Application;

use App\Entity\AfterSales\ExplodedView;
use App\Entity\AfterSales\Model;
use App\Entity\Product\Product;
use App\Yack\AfterSales\Application\ApplianceModels;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Parc installé écrit à la main. Les méthodes `test…` tournent sans vendor/ (modèles sans produit fini) ; les
 * méthodes marquées `#[Test]` sans ce préfixe construisent de vrais produits Sylius : PHPUnit seulement.
 *
 * @internal
 */
#[CoversClass(ApplianceModels::class)]
final class ApplianceModelsTest extends TestCase
{
    /** @var list<string> */
    private array $lookups = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->lookups = [];
    }

    public function testAnApplianceAbsentFromTheInstalledBaseGivesNull(): void
    {
        $this->assertNull($this->appliances([])->productCodes('ABC123456'));
        $this->assertSame(['ABC123456'], $this->lookups);
    }

    public function testAnEmptyInputIsNotLookedUp(): void
    {
        $this->assertNull($this->appliances([])->productCodes('  '));
        $this->assertSame([], $this->lookups);
    }

    public function testAKnownApplianceWithoutFinishedProductGivesAnEmptyList(): void
    {
        $this->assertSame([], $this->appliances(['YK19KAZ35S004567' => [self::modelWithoutProduct(), self::modelWithoutProduct()]])->productCodes('YK19KAZ35S004567'));
    }

    public function testTheInputIsPassedAsTypedToTheInstalledBase(): void
    {
        $this->appliances([])->productCodes('yk19-kaz35s 004567');

        $this->assertSame(['yk19-kaz35s 004567'], $this->lookups);
    }

    #[Test]
    public function codesOfTheFinishedProductsInTheOrderOfTheModels(): void
    {
        $appliances = $this->appliances(['YK19KAZ35S004567' => [
            new Model('SC-KAZ-I35', 'Mural Titane Wifi intégré 2,5 kW : unité intérieure', 'PAC air/air monosplit', self::product('SRK25ZS-WFT')),
            new Model('SC-KAZ-O35', 'Groupe extérieur Performance 2,5 kW : unité extérieure', 'PAC air/air monosplit', self::product('SRC25ZS-W2')),
        ]]);

        $this->assertSame(['SRK25ZS-WFT', 'SRC25ZS-W2'], $appliances->productCodes('YK19KAZ35S004567'));
    }

    #[Test]
    public function aProductSharedByTwoModelsIsGivenOnce(): void
    {
        $appliances = $this->appliances(['YK24HYD11S000321' => [
            new Model('SC-HYD-I11', 'Hydrolution : module intérieur', 'PAC air/eau split', self::product('FDCW-VNX-A')),
            new Model('SC-HYD-O11', 'Hydrolution : groupe extérieur', 'PAC air/eau split', self::product('FDCW-VNX-A')),
            new Model('SC-HYD-X11', 'Accessoire sans produit fini', 'PAC air/eau split'),
        ]]);

        $this->assertSame(['FDCW-VNX-A'], $appliances->productCodes('YK24HYD11S000321'));
    }

    private static function product(string $code): Product
    {
        $product = new Product();
        $product->setCode($code);

        return $product;
    }

    /** Modèle sans ses collections Doctrine ni produit fini : construit sans vendor/. */
    private static function modelWithoutProduct(): Model
    {
        $model = (new \ReflectionClass(Model::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Model::class, 'finishedProduct'))->setValue($model, null);

        return $model;
    }

    /** @param array<string, list<Model>> $installed n° de série => modèles */
    private function appliances(array $installed): ApplianceModels
    {
        $lookups = &$this->lookups;

        return new ApplianceModels(new class($installed, $lookups) implements AfterSalesCatalog {
            /**
             * @param array<string, list<Model>> $installed
             * @param list<string>               $lookups
             */
            public function __construct(private array $installed, private array &$lookups)
            {
            }

            public function findModels(string $serialOrServiceCode): array
            {
                $this->lookups[] = $serialOrServiceCode;

                return $this->installed[$serialOrServiceCode] ?? [];
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
        });
    }
}
