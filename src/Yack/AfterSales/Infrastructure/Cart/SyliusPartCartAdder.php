<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Infrastructure\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Adds a spare part to the regular Sylius cart, exactly like the product page does
 * (same "sylius.cart_item_add" event, hence same ECOM-only enforcement and same cart).
 */
final readonly class SyliusPartCartAdder
{
    /** @param CartItemFactoryInterface<OrderItemInterface> $cartItemFactory */
    public function __construct(
        #[Autowire(service: 'sylius.context.cart')]
        private CartContextInterface $cartContext,
        #[Autowire(service: 'sylius.factory.cart_item')]
        private CartItemFactoryInterface $cartItemFactory,
        #[Autowire(service: 'sylius.modifier.order_item_quantity')]
        private OrderItemQuantityModifierInterface $quantityModifier,
        #[Autowire(service: 'sylius.factory.add_to_cart_command')]
        private AddToCartCommandFactoryInterface $addToCartCommandFactory,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function add(ProductVariantInterface $variant, int $quantity): void
    {
        $cart = $this->cartContext->getCart();
        $cartItem = $this->cartItemFactory->createNew();
        $cartItem->setVariant($variant);
        $this->quantityModifier->modify($cartItem, max(1, $quantity));

        $command = $this->addToCartCommandFactory->createWithCartAndCartItem($cart, $cartItem);
        $this->eventDispatcher->dispatch(new GenericEvent($command), SyliusCartEvents::CART_ITEM_ADD);

        $this->entityManager->persist($cart);
        $this->entityManager->flush();
    }
}
