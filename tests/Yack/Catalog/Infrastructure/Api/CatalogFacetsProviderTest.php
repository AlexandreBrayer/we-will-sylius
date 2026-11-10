<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Infrastructure\Api;

use ApiPlatform\Metadata\Get;
use App\Yack\Account\Domain\CommercePermission;
use App\Yack\Catalog\Application\ListFacets;
use App\Yack\Catalog\Domain\CatalogFacetSource;
use App\Yack\Catalog\Domain\ProductFacetValues;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Catalog\Infrastructure\Api\CatalogFacetsProvider;
use App\Yack\Catalog\Infrastructure\Api\CatalogFacetsView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(CatalogFacetsProvider::class)]
final class CatalogFacetsProviderTest extends TestCase
{
    public function testACustomerWhoSeesPricesGetsThePriceBoundsAndFiltersOnPrice(): void
    {
        $view = $this->provide(true, 'yack_type=product&yack_price_min=1000&yack_price_max=2000');

        $this->assertSame('product', $view->type);
        $this->assertSame(['min' => 450.0, 'max' => 5200.0], $view->price);
        // Brand (Aerys, Yack): 1000 to 2000 euros.
        $this->assertSame([0, 1], array_column($view->facets[0]['values'], 'count'));
    }

    public function testVisitorsAndProCustomersNeitherSeeNorFilterOnPrices(): void
    {
        $view = $this->provide(false, 'yack_type=product&yack_price_min=1000&yack_price_max=2000');

        $this->assertNull($view->price);
        // Brand (Aerys, Yack): the price range is ignored, no product is left out by its price.
        $this->assertSame([1, 2], array_column($view->facets[0]['values'], 'count'));
    }

    public function testAnInvalidPriceIsIgnoredWithoutPricesAndRefusedWithThem(): void
    {
        $this->assertNull($this->provide(false, 'yack_price_min=abc')->price);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('yack_price_min : Prix minimum invalide');

        $this->provide(true, 'yack_price_min=abc');
    }

    public function testUnknownTypeHasNoFacetAndNoPrice(): void
    {
        $view = $this->provide(true, 'yack_type=autre');

        $this->assertSame([], $view->facets);
        $this->assertNull($view->price);
    }

    private function provide(bool $seesPrices, string $query): CatalogFacetsView
    {
        $source = new class() implements CatalogFacetSource {
            public function listedProducts(?ProductKind $kind): array
            {
                return [
                    new ProductFacetValues('Yack', 'Evolys', 6.0, 'R32', 1500.0),
                    new ProductFacetValues('Yack', 'Evolys', 16.0, 'R32', 5200.0),
                    new ProductFacetValues('Aerys', 'Kaze', 2.6, 'R32', 450.0),
                ];
            }
        };
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->with(CommercePermission::SeePrices->value)->willReturn($seesPrices);

        return (new CatalogFacetsProvider(new ListFacets($source), $authorizationChecker))
            ->provide(new Get(), [], ['request' => Request::create('/api/v2/shop/yack/catalog-facets?' . $query)]);
    }
}
