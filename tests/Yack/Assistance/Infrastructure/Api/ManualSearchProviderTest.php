<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Infrastructure\Api;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\AfterSales\Application\ApplianceModelsInterface;
use App\Yack\Assistance\Application\FindManuals;
use App\Yack\Assistance\Domain\Manual;
use App\Yack\Assistance\Domain\ManualLibrary;
use App\Yack\Assistance\Domain\ManualProduct;
use App\Yack\Assistance\Domain\ManualType;
use App\Yack\Assistance\Infrastructure\Api\AssistanceSwitch;
use App\Yack\Assistance\Infrastructure\Api\ManualSearchProvider;
use App\Yack\Assistance\Infrastructure\Api\ManualSearchView;
use App\Yack\Catalog\Application\ProductReferenceReaderInterface;
use App\Yack\Catalog\Domain\ProductReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ce test a besoin de Symfony et d'API Platform (vendor/) : PHPUnit seulement. Base, parc et catalogue écrits à la main.
 *
 * @internal
 */
#[CoversClass(ManualSearchProvider::class)]
#[CoversClass(ManualSearchView::class)]
final class ManualSearchProviderTest extends TestCase
{
    public function testAReferenceGivesTheContractOfTheShopApi(): void
    {
        $view = $this->provide(['q' => 'srk25zs w']);

        $this->assertSame('srk25zs w', $view->query);
        $this->assertSame(['read' => 'srk25zs w', 'canonical' => 'SRK25ZS-W', 'structured' => true, 'corrected' => false, 'brand' => 'MHI', 'brandSource' => 'catalogue'], $view->reference);
        $this->assertNull($view->serial);
        $this->assertSame(['code' => 'SRK25ZS-W', 'name' => 'SRK25ZS-W Mural - 2,5kW - R32', 'slug' => 'srk25zs-w-mural'], $view->product);
        $this->assertSame('exact', $view->level);
        $this->assertSame([[
            'level' => 'exact',
            'reason' => 'reference_identique',
            'source' => 'reference',
            'documents' => [[
                'id' => 'srk-zs-w-installation',
                'title' => 'Installation manual SRK/SRC ZS-W (test)',
                'type' => 'installation',
                'typeLabel' => 'Manuel d\'installation',
                'languages' => ['en', 'fr'],
                'url' => 'https://manuels.example/srk-zs-w.pdf',
                'official' => true,
                'manualNumber' => 'TEST-001',
                'year' => '2019',
                'pages' => 24,
                'models' => ['SRC25ZS-W', 'SRK25ZS-W', 'SRK35ZS-W'],
                'hasText' => true,
            ]],
        ]], $view->groups);
        $this->assertSame([], $view->choices);
        $this->assertTrue($view->askable);
    }

    public function testAKnownSerialLinksToTheAfterSalesPage(): void
    {
        $view = $this->provide(['q' => 'YK19KAZ35S004567']);

        $this->assertSame(['value' => 'YK19KAZ35S004567', 'known' => true, 'savUrl' => '/sav/YK19KAZ35S004567', 'productCodes' => ['SRK25ZS-WFT', 'SRC25ZS-W2'], 'conflict' => false], $view->serial);
        $this->assertNull($view->reference);
        $this->assertSame(['serial'], array_values(array_unique(array_column($view->groups, 'source'))));
    }

    public function testAConflictKeepsTheGroupsOfBothSources(): void
    {
        $view = $this->provide(['reference' => 'SRK35ZS-W', 'serial' => 'YK19KAZ35S004567']);

        $this->assertTrue($view->serial['conflict'] ?? null);
        $this->assertSame([['exact', 'serial'], ['exact', 'reference'], ['serie', 'serial']], array_map(static fn (array $group): array => [$group['level'], $group['source']], $view->groups));
    }

    public function testAnUnknownSerialHasNoAfterSalesLink(): void
    {
        $view = $this->provide(['serial' => 'abc123456']);

        $this->assertSame(['value' => 'ABC123456', 'known' => false, 'savUrl' => null, 'productCodes' => [], 'conflict' => false], $view->serial);
        $this->assertSame('aucun', $view->level);
        $this->assertSame([], $view->groups);
    }

    public function testAProductNameGivesCodeAndNameChoices(): void
    {
        $view = $this->provide(['q' => 'mural titane']);

        $this->assertSame([['code' => 'SRK25ZS-WT', 'name' => 'Mural Titane 2,5 kW (test)']], $view->choices);
        $this->assertSame([], $view->groups);
        $this->assertFalse($view->askable);
    }

    public function testAnEmptySearchIsRefusedOnTheQueryField(): void
    {
        foreach ([[], ['q' => ''], ['q' => ['tableau']], ['reference' => ' ', 'serial' => '']] as $parameters) {
            try {
                $this->provide($parameters);
                $this->fail('422 attendu');
            } catch (ValidationException $exception) {
                $violation = $exception->getConstraintViolationList()->get(0);
                $this->assertSame('q', $violation->getPropertyPath());
                $this->assertSame(FindManuals::REQUIRED_MESSAGE, $violation->getMessage());
            }
        }
    }

    public function testATooLongFieldIsRefusedOnItself(): void
    {
        try {
            $this->provide(['reference' => str_repeat('A', 101), 'serial' => 'YK19KAZ35S004567']);
            $this->fail('422 attendu');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
            $this->assertCount(1, $violations);
            $this->assertSame('reference', $violations->get(0)->getPropertyPath());
            $this->assertSame(FindManuals::TOO_LONG_MESSAGE, $violations->get(0)->getMessage());
        }
    }

    public function testTheSwitchOffAnswersNotFoundBeforeAnyCheck(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->provide([], enabled: false);
    }

    /** @param array<string, mixed> $parameters */
    private function provide(array $parameters, bool $enabled = true): ManualSearchView
    {
        $provider = new ManualSearchProvider(new AssistanceSwitch($enabled), new FindManuals(self::library(), self::appliances(), self::catalog()));

        return $provider->provide(new Get(), [], ['request' => new Request($parameters)]);
    }

    private static function library(): ManualLibrary
    {
        return new class implements ManualLibrary {
            public function documents(): array
            {
                return [
                    new Manual('srk-zs-w-installation', 'Installation manual SRK/SRC ZS-W (test)', ManualType::Installation, ['en', 'fr'], 'https://manuels.example/srk-zs-w.pdf', true,
                        ['SRC25ZS-W', 'SRK25ZS-W', 'SRK35ZS-W'], ['SRK25ZS-W'], 'MHI', 'TEST-001', '2019', 24, 'textes/srk-zs-w-installation.md', '12-19'),
                    new Manual('src-zs-w2-installation', 'Installation manual SRC25ZS-W2 (test)', ManualType::Installation, ['en'], 'https://manuels.example/src-zs-w2.pdf', true,
                        ['SRC25ZS-W2'], ['SRC25ZS-W2'], 'MHI'),
                ];
            }

            public function products(): array
            {
                return [
                    new ManualProduct('SRK25ZS-W', 'Mural ZS 2,5 kW (test)', 'MHI'),
                    new ManualProduct('SRK25ZS-WT', 'Mural Titane 2,5 kW (test)', 'MHI'),
                ];
            }
        };
    }

    private static function appliances(): ApplianceModelsInterface
    {
        return new class implements ApplianceModelsInterface {
            public function productCodes(string $serialOrServiceCode): ?array
            {
                return 'YK19KAZ35S004567' === strtoupper($serialOrServiceCode) ? ['SRK25ZS-WFT', 'SRC25ZS-W2'] : null;
            }
        };
    }

    private static function catalog(): ProductReferenceReaderInterface
    {
        return new class implements ProductReferenceReaderInterface {
            public function find(string $printedReference): ?ProductReference
            {
                return 'SRK25ZS-W' === $printedReference ? new ProductReference('SRK25ZS-W', 'SRK25ZS-W Mural - 2,5kW - R32', 'srk25zs-w-mural', 'MHI') : null;
            }
        };
    }
}
