<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Fixture;

use App\Entity\Customer\Customer;
use App\Yack\Catalog\Application\ProductKindResolverInterface;
use App\Yack\Catalog\Domain\OnlineSale;
use App\Yack\Catalog\Domain\ProductKind;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Shipping\ShipmentTransitions;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Demo orders of the last months (back-office dashboard, order list, "Mes commandes"): planned by
 * DemoOrderPlanner, then placed through the real Sylius checkout (order processors: prices, taxes,
 * shipping, escompte; state machines: numbers, payment and shipping states), and finally dated as
 * planned. Only products sold online, with a price in the channel.
 *
 * The catalogue stock stays the one of the feed (the ERP stock already accounts for past sales):
 * stock tracking is switched off while the orders are placed, then switched back on.
 *
 * Sylius keeps the sales statistics of the dashboard 30 minutes in cache.app (var/share, which a
 * reset of var/cache keeps): the pool is emptied after the load, so the chart shows these orders.
 *
 * @phpstan-import-type Settings from DemoOrderPlanner
 *
 * @phpstan-type Options array{
 *     seed: int, count: int, days: int, season: non-empty-list<int>, histories: array<string, int>, business_weight: int, individual_weight: int,
 *     payment_methods: array<string, array{weight: int, paid_after_days: list<int>, individuals: bool}>,
 *     shipping_methods: array<string, array{weight: int, shipped_after_days: list<int>, tracking: bool, individuals: bool}>,
 *     cancelled_percent: int, refunded_percent: int, overdue_percent: int, notes: list<string>
 * }
 */
final class YackDemoOrderFixture extends AbstractFixture
{
    /** Customer group whose customers order online (ECOM). */
    private const ORDERING_GROUP = 'ecom';

    /** Flush every N orders (and after the first one). */
    private const BATCH = 100;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface>               $channels
     * @param CustomerRepositoryInterface<CustomerInterface>             $customers
     * @param ProductVariantRepositoryInterface<ProductVariantInterface> $variants
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface>   $paymentMethods
     * @param ShippingMethodRepositoryInterface<ShippingMethodInterface> $shippingMethods
     * @param FactoryInterface<OrderInterface>                           $orderFactory
     * @param FactoryInterface<OrderItemInterface>                       $orderItemFactory
     * @param FactoryInterface<AddressInterface>                         $addressFactory
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'sylius.repository.channel')]
        private readonly ChannelRepositoryInterface $channels,
        #[Autowire(service: 'sylius.repository.customer')]
        private readonly CustomerRepositoryInterface $customers,
        #[Autowire(service: 'sylius.repository.product_variant')]
        private readonly ProductVariantRepositoryInterface $variants,
        #[Autowire(service: 'sylius.repository.payment_method')]
        private readonly PaymentMethodRepositoryInterface $paymentMethods,
        #[Autowire(service: 'sylius.repository.shipping_method')]
        private readonly ShippingMethodRepositoryInterface $shippingMethods,
        #[Autowire(service: 'sylius.factory.order')]
        private readonly FactoryInterface $orderFactory,
        #[Autowire(service: 'sylius.factory.order_item')]
        private readonly FactoryInterface $orderItemFactory,
        #[Autowire(service: 'sylius.factory.address')]
        private readonly FactoryInterface $addressFactory,
        #[Autowire(service: 'sylius.modifier.order_item_quantity')]
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly StateMachineInterface $stateMachine,
        private readonly ProductKindResolverInterface $productKinds,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.yack.channel_code')]
        private readonly string $channelCode,
        #[Autowire(param: 'app.yack.locale')]
        private readonly string $localeCode,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $appCache,
    ) {
    }

    public function getName(): string
    {
        return 'yack_demo_order';
    }

    /** @param Options $options */
    public function load(array $options): void
    {
        $channel = $this->channels->findOneByCode($this->channelCode);
        if (!$channel instanceof ChannelInterface) {
            throw new \InvalidArgumentException(sprintf('Channel "%s" not found: load the channel fixture first.', $this->channelCode));
        }

        $paymentMethods = self::byCode($this->paymentMethods->findEnabledForChannel($channel), array_keys($options['payment_methods']), 'payment');
        $shippingMethods = self::byCode($this->shippingMethods->findEnabledForChannel($channel), array_keys($options['shipping_methods']), 'shipping');
        [$customers, $plannedCustomers] = $this->customers($options);
        [$variants, $products] = $this->products($channel);

        $now = $this->clock->now();
        $planned = (new DemoOrderPlanner(self::settings($options)))->plan($plannedCustomers, $products, $now);

        $untracked = [];
        foreach ($variants as $variant) {
            if ($variant->isTracked()) {
                $variant->setTracked(false);
                $untracked[] = $variant;
            }
        }

        foreach ($planned as $index => $plan) {
            $this->entityManager->persist($this->place(
                $plan,
                $channel,
                $customers[$plan->customerEmail],
                $variants,
                $paymentMethods[$plan->paymentMethod],
                $shippingMethods[$plan->shippingMethod],
            ));
            // First flush right after the first order: Sylius creates the order number sequence then,
            // and looks it up in the database for the next numbers.
            if (0 === $index % self::BATCH) {
                $this->entityManager->flush();
            }
        }

        foreach ($untracked as $variant) {
            $variant->setTracked(true);
        }
        $this->entityManager->flush();
        $this->appCache->clear();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $range = static fn (string $name): ArrayNodeDefinition => (new ArrayNodeDefinition($name))
            ->isRequired()
            ->integerPrototype()->min(0)->end()
            ->validate()
                ->ifTrue(static fn (array $range): bool => 2 !== \count($range) || $range[0] > $range[1])
                ->thenInvalid('Expected [min, max] with min <= max, got %s.')
            ->end();

        $optionsNode
            ->children()
                ->integerNode('seed')->defaultValue(20261108)->end()
                ->integerNode('count')->min(0)->defaultValue(150)->end()
                ->integerNode('days')->min(1)->defaultValue(365)->end()
                // Monthly weights of the order dates, January first
                ->arrayNode('season')
                    ->integerPrototype()->min(1)->end()
                    ->defaultValue([6, 7, 9, 11, 14, 17, 18, 13, 10, 8, 7, 6])
                    ->validate()
                        ->ifTrue(static fn (array $season): bool => 12 !== \count($season))
                        ->thenInvalid('Expected 12 monthly weights, got %s.')
                    ->end()
                ->end()
                ->arrayNode('histories')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('email')
                    ->integerPrototype()->min(0)->end()
                ->end()
                ->integerNode('business_weight')->min(0)->defaultValue(4)->end()
                ->integerNode('individual_weight')->min(0)->defaultValue(1)->end()
                ->arrayNode('payment_methods')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->isRequired()->end()
                            ->append($range('paid_after_days'))
                            ->booleanNode('individuals')->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('shipping_methods')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->isRequired()->end()
                            ->append($range('shipped_after_days'))
                            ->booleanNode('tracking')->defaultTrue()->end()
                            ->booleanNode('individuals')->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('cancelled_percent')->min(0)->max(100)->defaultValue(5)->end()
                ->integerNode('refunded_percent')->min(0)->max(100)->defaultValue(3)->end()
                ->integerNode('overdue_percent')->min(0)->max(100)->defaultValue(4)->end()
                ->arrayNode('notes')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
            ->end()
        ;
    }

    /** @param array<string, ProductVariantInterface> $variants */
    private function place(
        PlannedOrder $plan,
        ChannelInterface $channel,
        CustomerInterface $customer,
        array $variants,
        PaymentMethodInterface $paymentMethod,
        ShippingMethodInterface $shippingMethod,
    ): OrderInterface {
        $order = $this->orderFactory->createNew();
        $order->setChannel($channel);
        $order->setCurrencyCode($channel->getBaseCurrency()?->getCode());
        $order->setLocaleCode($this->localeCode);
        $order->setCustomerWithAuthorization($customer);
        $order->setCreatedAt(self::mutable($plan->placedAt->modify('-12 minutes')));

        foreach ($plan->lines as $variantCode => $quantity) {
            $item = $this->orderItemFactory->createNew();
            $item->setVariant($variants[$variantCode]);
            $this->quantityModifier->modify($item, $quantity);
            $order->addItem($item);
        }

        $order->setBillingAddress($this->address($customer));
        $order->setShippingAddress($this->address($customer));
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);
        foreach ($order->getShipments() as $shipment) {
            $shipment->setMethod($shippingMethod);
        }
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        foreach ($order->getPayments() as $payment) {
            $payment->setMethod($paymentMethod);
        }
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        $order->setNotes($plan->notes);
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $order->setCheckoutCompletedAt(self::mutable($plan->placedAt));

        foreach ($order->getPayments() as $payment) {
            $payment->setCreatedAt(self::mutable($plan->placedAt));
            $payment->setUpdatedAt(self::mutable($plan->refundedAt ?? $plan->paidAt ?? $plan->cancelledAt ?? $plan->placedAt));
            if (null !== $plan->paidAt) {
                $this->applyIfPossible($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
            }
            if (null !== $plan->refundedAt) {
                $this->applyIfPossible($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
            }
        }

        foreach ($order->getShipments() as $shipment) {
            $shipment->setCreatedAt(self::mutable($plan->placedAt));
            $shipment->setUpdatedAt(self::mutable($plan->shippedAt ?? $plan->cancelledAt ?? $plan->placedAt));
            if (null !== $plan->shippedAt) {
                $shipment->setTracking($plan->tracking);
                $this->applyIfPossible($shipment, ShipmentTransitions::GRAPH, ShipmentTransitions::TRANSITION_SHIP);
                $shipment->setShippedAt(self::mutable($plan->shippedAt));
            }
        }

        if (null !== $plan->cancelledAt) {
            $this->applyIfPossible($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);
        }
        $order->setUpdatedAt(self::mutable($plan->lastEventAt()));

        return $order;
    }

    private function applyIfPossible(object $subject, string $graph, string $transition): void
    {
        if ($this->stateMachine->can($subject, $graph, $transition)) {
            $this->stateMachine->apply($subject, $graph, $transition);
        }
    }

    /** Copy of the customer's default address (billing and delivery). */
    private function address(CustomerInterface $customer): AddressInterface
    {
        $default = $customer->getDefaultAddress();
        if (!$default instanceof AddressInterface) {
            throw new \InvalidArgumentException(sprintf('Customer "%s" has no default address: give one in the customer fixtures.', $customer->getEmail()));
        }

        $address = $this->addressFactory->createNew();
        $address->setFirstName($default->getFirstName());
        $address->setLastName($default->getLastName());
        $address->setCompany($default->getCompany());
        $address->setPhoneNumber($default->getPhoneNumber());
        $address->setStreet($default->getStreet());
        $address->setPostcode($default->getPostcode());
        $address->setCity($default->getCity());
        $address->setCountryCode($default->getCountryCode());

        return $address;
    }

    /**
     * Customers who order: the guaranteed histories, plus every ECOM customer (company or individual).
     *
     * @param Options $options
     *
     * @return array{array<string, CustomerInterface>, list<DemoOrderCustomer>}
     */
    private function customers(array $options): array
    {
        $histories = array_change_key_case($options['histories'], \CASE_LOWER);
        $byEmail = [];
        $planned = [];
        foreach ($this->customers->findBy([], ['emailCanonical' => 'ASC']) as $customer) {
            $email = (string) $customer->getEmailCanonical();
            $minimum = $histories[$email] ?? 0;
            unset($histories[$email]);
            $ordering = self::ORDERING_GROUP === $customer->getGroup()?->getCode();
            if (0 === $minimum && !$ordering) {
                continue;
            }

            $business = $customer instanceof Customer && null !== $customer->getCompany();
            $registeredAt = $customer->getCreatedAt();
            $byEmail[$email] = $customer;
            $planned[] = new DemoOrderCustomer(
                $email,
                null === $registeredAt ? $this->clock->now() : \DateTimeImmutable::createFromInterface($registeredAt),
                $business,
                $minimum,
                // A guaranteed history is exact: no other order on top of it.
                $ordering && 0 === $minimum ? ($business ? $options['business_weight'] : $options['individual_weight']) : 0,
            );
        }

        if ([] !== $histories) {
            throw new \InvalidArgumentException(sprintf('Customer "%s" not found: load the customer fixtures first.', array_key_first($histories)));
        }

        return [$byEmail, $planned];
    }

    /**
     * Variants that can be ordered online in the channel (enabled, priced, sold online), by code.
     *
     * @return array{array<string, ProductVariantInterface>, list<DemoOrderProduct>}
     */
    private function products(ChannelInterface $channel): array
    {
        $variants = [];
        $products = [];
        foreach ($this->variants->findBy([], ['code' => 'ASC']) as $variant) {
            $product = $variant->getProduct();
            $price = $variant->getChannelPricingForChannel($channel)?->getPrice();
            $code = $variant->getCode();
            if (null === $code || null === $price || !$product instanceof ProductInterface || !$variant->isEnabled() || !$product->isEnabled() || !$product->hasChannel($channel)) {
                continue;
            }
            $soldOnline = null;
            foreach ($product->getAttributes() as $attributeValue) {
                if ($attributeValue instanceof ProductAttributeValueInterface && OnlineSale::ATTRIBUTE === $attributeValue->getCode()) {
                    $soldOnline = $attributeValue->getValue();
                }
            }
            if (!OnlineSale::isSoldOnline($soldOnline)) {
                continue;
            }

            $variants[$code] = $variant;
            $products[] = new DemoOrderProduct($code, $price, ProductKind::SparePart === $this->productKinds->kindOf($product));
        }

        return [$variants, $products];
    }

    /**
     * @template T of PaymentMethodInterface|ShippingMethodInterface
     *
     * @param array<T>     $enabled
     * @param list<string> $codes
     *
     * @return array<string, T>
     */
    private static function byCode(array $enabled, array $codes, string $kind): array
    {
        $byCode = [];
        foreach ($enabled as $method) {
            $byCode[(string) $method->getCode()] = $method;
        }
        foreach ($codes as $code) {
            if (!isset($byCode[$code])) {
                throw new \InvalidArgumentException(sprintf('No enabled %s method "%s" in the channel.', $kind, $code));
            }
        }

        return $byCode;
    }

    /**
     * @param Options $options
     *
     * @return Settings
     */
    private static function settings(array $options): array
    {
        return [
            'seed' => $options['seed'],
            'count' => $options['count'],
            'days' => $options['days'],
            'season' => $options['season'],
            'payment_methods' => $options['payment_methods'],
            'shipping_methods' => $options['shipping_methods'],
            'cancelled_percent' => $options['cancelled_percent'],
            'refunded_percent' => $options['refunded_percent'],
            'overdue_percent' => $options['overdue_percent'],
            'notes' => $options['notes'],
        ];
    }

    private static function mutable(\DateTimeImmutable $date): \DateTime
    {
        return \DateTime::createFromImmutable($date);
    }
}
