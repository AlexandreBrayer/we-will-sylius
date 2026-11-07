<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\EventListener;

use App\Yack\Catalog\Domain\OnlineSale;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Server-side enforcement of the online sale rule on the shop API: adding a product that is not
 * sold online to a cart (POST /api/v2/shop/orders/{token}/items) is refused with a 422.
 */
final readonly class OnlineSaleCartListener
{
    private const ITEMS_PATH = '#^/orders/[^/]+/items$#';

    /** @param RepositoryInterface<ProductVariantInterface> $variantRepository */
    public function __construct(
        #[Autowire(service: 'sylius.repository.product_variant')]
        private RepositoryInterface $variantRepository,
        #[Autowire(param: 'sylius.security.api_shop_route')]
        private string $apiShopRoute,
    ) {
    }

    /** After the ECOM check of Account (7), before API Platform reads the resource (ReadListener, 4). */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
    public function onApiRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST')) {
            return;
        }

        $path = $request->getPathInfo();
        if (!str_starts_with($path, $this->apiShopRoute . '/') || 1 !== preg_match(self::ITEMS_PATH, substr($path, strlen($this->apiShopRoute)))) {
            return;
        }

        $code = self::variantCode($request->getContent());
        $variant = null === $code ? null : $this->variantRepository->findOneBy(['code' => $code]);
        $product = $variant instanceof ProductVariantInterface ? $variant->getProduct() : null;
        if ($product instanceof ProductInterface && !OnlineSale::isSoldOnline(self::flag($product))) {
            throw new UnprocessableEntityHttpException(OnlineSale::NOT_SOLD_ONLINE);
        }
    }

    /** Code of the variant added (body `{"productVariant": "/api/v2/shop/product-variants/{code}"}`), null when unreadable. */
    public static function variantCode(string $body): ?string
    {
        $payload = json_decode($body, true);
        $iri = is_array($payload) ? ($payload['productVariant'] ?? null) : null;
        if (!is_string($iri) || '' === trim($iri)) {
            return null;
        }
        $position = strrpos($iri, '/');
        $code = trim(rawurldecode(false === $position ? $iri : substr($iri, $position + 1)));

        return '' === $code ? null : $code;
    }

    private static function flag(ProductInterface $product): mixed
    {
        foreach ($product->getAttributes() as $attributeValue) {
            if ($attributeValue instanceof ProductAttributeValueInterface && OnlineSale::ATTRIBUTE === $attributeValue->getCode()) {
                return $attributeValue->getValue();
            }
        }

        return null;
    }
}
