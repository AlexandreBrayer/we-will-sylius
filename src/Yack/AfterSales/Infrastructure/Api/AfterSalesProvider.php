<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\AfterSales\ExplodedView;
use App\Entity\AfterSales\Model;
use App\Yack\Account\Domain\CommercePermission;
use App\Yack\AfterSales\Application\SerialLookup;
use App\Yack\AfterSales\Domain\AfterSalesCatalog;
use App\Yack\Catalog\Application\VariantStockReaderInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @implements ProviderInterface<SerialLookupView|ModelView|ExplodedViewView>
 */
final readonly class AfterSalesProvider implements ProviderInterface
{
    public function __construct(
        private SerialLookup $serialLookup,
        private AfterSalesCatalog $catalog,
        private VariantStockReaderInterface $variantStock,
        #[Autowire(service: 'sylius.calculator.product_variant_price')]
        private ProductVariantPricesCalculatorInterface $priceCalculator,
        private ChannelContextInterface $channelContext,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Packages $packages,
        private UrlHelper $urlHelper,
        private ClockInterface $clock,
        #[Autowire(param: 'sylius.security.api_shop_route')]
        private string $apiShopRoute,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SerialLookupView|ModelView|ExplodedViewView|null
    {
        return match ($operation->getClass()) {
            SerialLookupView::class => $this->serial(self::stringVariable($uriVariables, 'query')),
            ModelView::class => $this->model(self::stringVariable($uriVariables, 'serviceCode')),
            ExplodedViewView::class => $this->explodedView((int) self::stringVariable($uriVariables, 'id')),
            default => null,
        };
    }

    private function serial(string $query): ?SerialLookupView
    {
        $lookup = ($this->serialLookup)($query);
        if ([] === $lookup->models) {
            return null;
        }

        $warranty = null;
        if (null !== $lookup->warranty) {
            $now = $this->clock->now();
            $warranty = [
                'serialNumber' => $lookup->warranty->serialNumber,
                'commissionedAt' => $lookup->warranty->commissionedAt->format('Y-m-d'),
                'partsCoverageEnd' => $lookup->warranty->partsCoverageEnd->format('Y-m-d'),
                'compressorCoverageEnd' => $lookup->warranty->compressorCoverageEnd->format('Y-m-d'),
                'partsCovered' => $lookup->warranty->isPartsCoveredAt($now),
                'compressorCovered' => $lookup->warranty->isCompressorCoveredAt($now),
                'source' => $lookup->warranty->source,
            ];
        }

        return new SerialLookupView(
            $lookup->query,
            $lookup->serial,
            $lookup->isServiceCode(),
            $warranty,
            array_map(fn (Model $model): array => [
                'serviceCode' => $model->getServiceCode(),
                'name' => $model->getName(),
                'family' => $model->getFamily(),
                'explodedViewCount' => $model->getExplodedViews()->count(),
                'partCount' => $model->countParts(),
                'finishedProduct' => $this->productSummary($model->getFinishedProduct()),
            ], $lookup->models),
        );
    }

    private function model(string $serviceCode): ?ModelView
    {
        $model = $this->catalog->findModelByServiceCode($serviceCode);
        if (null === $model) {
            return null;
        }

        $views = [];
        foreach ($model->getExplodedViews() as $view) {
            $views[] = [
                'id' => (int) $view->getId(),
                'name' => $view->getName(),
                'imagePath' => $this->packages->getUrl($view->getImagePath()),
                'imageUrl' => $this->imageUrl($view),
                'partCount' => $view->getItems()->count(),
            ];
        }

        return new ModelView(
            $model->getServiceCode(),
            $model->getName(),
            $model->getFamily(),
            $model->countParts(),
            $this->productSummary($model->getFinishedProduct()),
            $views,
        );
    }

    private function explodedView(int $id): ?ExplodedViewView
    {
        $view = $this->catalog->findExplodedViewById($id);
        if (null === $view) {
            return null;
        }

        $model = $view->getModel();
        $canSeePrices = $this->authorizationChecker->isGranted(CommercePermission::SeePrices->value);

        $items = [];
        foreach ($view->getItems() as $item) {
            $part = $item->getPart();
            $variant = $part->getEnabledVariants()->first();
            $variant = $variant instanceof ProductVariantInterface ? $variant : null;
            $sellable = null !== $variant && $part->isEnabled();

            $stock = $this->variantStock->of($variant);
            if (!$part->isEnabled()) {
                $stock = $stock->withdrawn();
            }

            $items[] = [
                'marker' => $item->getMarker(),
                'x' => $item->getX(),
                'y' => $item->getY(),
                'quantity' => $item->getQuantity(),
                'part' => [
                    'code' => $part->getCode(),
                    'name' => $part->getName(),
                    'slug' => $part->getSlug(),
                    'enabled' => $part->isEnabled(),
                    'variant' => $sellable ? sprintf('%s/product-variants/%s', $this->apiShopRoute, rawurlencode((string) $variant->getCode())) : null,
                    'variantCode' => $sellable ? $variant->getCode() : null,
                    'price' => $canSeePrices && $sellable
                        ? $this->priceCalculator->calculate($variant, ['channel' => $this->channelContext->getChannel()])
                        : null,
                    'stock' => $stock->toArray(),
                ],
            ];
        }

        $views = [];
        foreach ($model->getExplodedViews() as $modelView) {
            $views[] = ['id' => (int) $modelView->getId(), 'name' => $modelView->getName()];
        }

        return new ExplodedViewView(
            (int) $view->getId(),
            $view->getName(),
            $this->packages->getUrl($view->getImagePath()),
            $this->imageUrl($view),
            ['serviceCode' => $model->getServiceCode(), 'name' => $model->getName(), 'family' => $model->getFamily()],
            $views,
            $items,
        );
    }

    /** @param array<string, mixed> $uriVariables */
    private static function stringVariable(array $uriVariables, string $name): string
    {
        $value = $uriVariables[$name] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function imageUrl(ExplodedView $view): string
    {
        return $this->urlHelper->getAbsoluteUrl($this->packages->getUrl($view->getImagePath()));
    }

    /** @return array{code: string|null, slug: string|null, name: string|null}|null */
    private function productSummary(?ProductInterface $product): ?array
    {
        if (null === $product) {
            return null;
        }

        return ['code' => $product->getCode(), 'slug' => $product->getSlug(), 'name' => $product->getName()];
    }
}
