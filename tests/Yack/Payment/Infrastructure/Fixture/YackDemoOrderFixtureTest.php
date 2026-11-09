<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Fixture;

use App\Entity\Addressing\Address;
use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Currency\Currency;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerGroup;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Entity\Shipping\ShippingMethod;
use App\Yack\Catalog\Application\ProductKindResolverInterface;
use App\Yack\Catalog\Domain\ProductKind;
use App\Yack\Payment\Infrastructure\Fixture\YackDemoOrderFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[CoversClass(YackDemoOrderFixture::class)]
final class YackDemoOrderFixtureTest extends TestCase
{
    private const NOW = '2026-11-09 10:30:00';

    /** @var list<object> */
    private array $persisted = [];

    private FakeOrderStateMachine $stateMachine;

    /** @var array<string, ProductVariant> */
    private array $variants = [];

    /** @var array<string, int> */
    private array $quantities = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->stateMachine = new FakeOrderStateMachine();
    }

    public function testOrdersArePlacedThroughTheCheckoutThenDatedAsPlanned(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('clear');
        $fixture = $this->fixture(cache: $cache);

        $fixture->load(self::options($fixture, ['count' => 12, 'histories' => ['ecom@yack.test' => 3]]));

        /** @var list<Order> $orders */
        $orders = array_values(array_filter($this->persisted, static fn (object $o): bool => $o instanceof Order));
        $this->assertSame('yack_demo_order', $fixture->getName());
        $this->assertCount(12, $orders);
        $this->assertSame(
            ['sylius_order_checkout.address', 'sylius_order_checkout.select_shipping', 'sylius_order_checkout.select_payment', 'sylius_order_checkout.complete'],
            \array_slice($this->stateMachine->applied, 0, 4),
        );

        $now = new \DateTimeImmutable(self::NOW);
        $emails = [];
        $codes = [];
        $previous = null;
        foreach ($orders as $order) {
            $emails[] = $order->getCustomer()?->getEmail();
            $completedAt = $order->getCheckoutCompletedAt();
            $this->assertNotNull($completedAt);
            $this->assertLessThan($now, $completedAt, 'Date de la commande planifiée, pas celle du chargement');
            $this->assertLessThan($completedAt, $order->getCreatedAt());
            $this->assertGreaterThanOrEqual($completedAt, $order->getUpdatedAt());
            if (null !== $previous) {
                $this->assertGreaterThanOrEqual($previous, $completedAt, 'Numéros dans l\'ordre chronologique');
            }
            $previous = $completedAt;
            $this->assertSame('EUR', $order->getCurrencyCode());
            $this->assertSame('fr_FR', $order->getLocaleCode());
            $this->assertNotNull($order->getNumber());
            $this->assertFalse($order->isCreatedByGuest());

            $customer = $order->getCustomer();
            $this->assertInstanceOf(Customer::class, $customer);
            $this->assertSame($customer->getCompany(), $order->getBillingAddress()?->getCompany());
            $this->assertSame('13100', $order->getShippingAddress()?->getPostcode());
            $this->assertNotSame($order->getBillingAddress(), $order->getShippingAddress());

            $shipment = $order->getShipments()->first();
            $this->assertInstanceOf(ShipmentInterface::class, $shipment);
            $this->assertNotNull($shipment->getMethod());
            $this->assertNotNull($order->getLastPayment()?->getMethod());
            if (ShipmentInterface::STATE_SHIPPED === $shipment->getState()) {
                $this->assertLessThan($now, $shipment->getShippedAt());
                $this->assertGreaterThan($completedAt, $shipment->getShippedAt());
                $this->assertSame('yack_transport' === $shipment->getMethod()?->getCode(), null !== $shipment->getTracking());
            }
            foreach ($order->getItems() as $item) {
                $this->assertGreaterThan(0, $this->quantities[spl_object_hash($item)]);
                $codes[] = $item->getVariant()?->getCode();
            }
        }
        $this->assertCount(3, array_keys($emails, 'ecom@yack.test', true), 'Historique exact du compte de démo');
        $this->assertContains('morvan@exemple.test', $emails);
        $this->assertNotContains('pro@yack.test', $emails, 'Un PRO sans historique garanti ne commande pas');
        $this->assertSame([], array_intersect(['SANS-PRIX', 'ARRET', 'VARIANTE-OFF'], $codes), 'Seulement les variantes vendables en ligne');
        $this->assertContains('YKP-1', $codes);

        foreach ($this->variants as $code => $variant) {
            $this->assertTrue($variant->isTracked(), $code . ' : suivi du stock rétabli');
        }
        $this->assertSame(['00001' => 5], ['00001' => $this->variants['00001']->getOnHand()], 'Stock du flux inchangé');
    }

    public function testTheSuiteOptionsAreValid(): void
    {
        /** @var array{sylius_fixtures: array{suites: array{yack: array{fixtures: array{yack_demo_order: array{priority: int, options: array<string, mixed>}, payment_method: array{options: array{custom: array<string, mixed>}}, shipping_method: array{options: array{custom: array<string, mixed>}}}}}}} $yaml */
        $yaml = Yaml::parseFile(\dirname(__DIR__, 5) . '/config/yack/fixtures.yaml');
        $fixtures = $yaml['sylius_fixtures']['suites']['yack']['fixtures'];
        $fixture = $this->fixture();

        /** @var array{count: int, days: int, season: list<int>, histories: array<string, int>, payment_methods: array<string, mixed>, shipping_methods: array<string, mixed>} $options */
        $options = self::options($fixture, $fixtures['yack_demo_order']['options']);

        $this->assertLessThan(0, $fixtures['yack_demo_order']['priority'], 'En fin de suite');
        $this->assertLessThanOrEqual(250, $options['count'], 'Chargement rapide');
        $this->assertSame(365, $options['days']);
        $this->assertCount(12, $options['season']);
        $this->assertGreaterThan($options['season'][0], $options['season'][6], 'Plus de climatisation en été qu\'en hiver');
        $this->assertSame(array_keys($fixtures['payment_method']['options']['custom']), array_intersect(array_keys($fixtures['payment_method']['options']['custom']), array_keys($options['payment_methods'])));
        $this->assertSame([], array_diff(array_keys($options['shipping_methods']), array_keys($fixtures['shipping_method']['options']['custom'])));
        $this->assertSame(['ecom@yack.test', 'pro@yack.test', 'pro2@yack.test'], array_keys($options['histories']));
    }

    public function testARangeMustGoUp(): void
    {
        $fixture = $this->fixture();

        $this->expectException(InvalidConfigurationException::class);

        self::options($fixture, ['payment_methods' => ['virement_15j' => ['weight' => 1, 'paid_after_days' => [9, 3]]]]);
    }

    public function testTheSeasonHasTwelveMonths(): void
    {
        $fixture = $this->fixture();

        $this->expectException(InvalidConfigurationException::class);

        self::options($fixture, ['season' => [1, 2, 3]]);
    }

    public function testWithoutChannelNothingIsLoaded(): void
    {
        $fixture = $this->fixture(channel: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Channel "YACK_WEB" not found');

        $fixture->load(self::options($fixture, []));
    }

    public function testAMethodMustBeEnabledInTheChannel(): void
    {
        $fixture = $this->fixture();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No enabled payment method "cheque"');

        $fixture->load(self::options($fixture, ['payment_methods' => ['cheque' => ['weight' => 1, 'paid_after_days' => [1, 2]]]]));
    }

    public function testAGuaranteedHistoryNeedsItsCustomer(): void
    {
        $fixture = $this->fixture();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer "personne@yack.test" not found');

        $fixture->load(self::options($fixture, ['histories' => ['personne@yack.test' => 2]]));
    }

    public function testACustomerWithoutAddressCannotOrder(): void
    {
        $fixture = $this->fixture(withAddress: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has no default address');

        $fixture->load(self::options($fixture, ['count' => 1]));
    }

    private function fixture(bool $channel = true, bool $withAddress = true, ?CacheItemPoolInterface $cache = null): YackDemoOrderFixture
    {
        $yackWeb = new Channel();
        $yackWeb->setCode('YACK_WEB');
        $euro = new Currency();
        $euro->setCode('EUR');
        $yackWeb->setBaseCurrency($euro);

        $channels = $this->createStub(ChannelRepositoryInterface::class);
        $channels->method('findOneByCode')->willReturn($channel ? $yackWeb : null);

        $paymentMethods = $this->createStub(PaymentMethodRepositoryInterface::class);
        $paymentMethods->method('findEnabledForChannel')->willReturn(array_map(self::paymentMethod(...), ['virement_15j', 'carte', 'virement_echeance']));
        $shippingMethods = $this->createStub(ShippingMethodRepositoryInterface::class);
        $shippingMethods->method('findEnabledForChannel')->willReturn(array_map(self::shippingMethod(...), ['yack_transport', 'yack_agence']));

        $customers = $this->createStub(CustomerRepositoryInterface::class);
        $customers->method('findBy')->willReturn([
            self::customer('ecom@yack.test', 'ecom', 'Clim Express', 900, $withAddress),
            self::customer('morvan@exemple.test', 'ecom', 'Atelier Thermique Morvan', 700, $withAddress),
            self::customer('martine@exemple.test', 'ecom', null, 300, $withAddress),
            self::customer('pro@yack.test', 'pro', 'Provence Clim', 500, $withAddress),
        ]);

        $this->variants = [];
        foreach ([['00001', 99_090, true, true], ['00002', 420_000, true, true], ['YKP-1', 2_990, true, true], ['SANS-PRIX', null, true, true], ['ARRET', 1_000, false, true], ['VARIANTE-OFF', 1_000, true, false]] as [$code, $price, $productEnabled, $variantEnabled]) {
            $product = new Product();
            $product->setCode($code);
            $product->setEnabled($productEnabled);
            $product->addChannel($yackWeb);
            $variant = new ProductVariant();
            $variant->setCode($code);
            $variant->setEnabled($variantEnabled);
            $variant->setTracked(true);
            $variant->setOnHand(5);
            $product->addVariant($variant);
            if (null !== $price) {
                $pricing = new ChannelPricing();
                $pricing->setChannelCode('YACK_WEB');
                $pricing->setPrice($price);
                $variant->addChannelPricing($pricing);
            }
            $this->variants[$code] = $variant;
        }
        $variants = $this->createStub(ProductVariantRepositoryInterface::class);
        $variants->method('findBy')->willReturn(array_values($this->variants));

        $kinds = $this->createStub(ProductKindResolverInterface::class);
        $kinds->method('kindOf')->willReturnCallback(static fn (ProductInterface $p): ProductKind => str_starts_with((string) $p->getCode(), 'YKP') ? ProductKind::SparePart : ProductKind::Finished);

        $quantities = $this->createStub(OrderItemQuantityModifierInterface::class);
        $quantities->method('modify')->willReturnCallback(function (OrderItem $item, int $quantity): void {
            $this->quantities[spl_object_hash($item)] = $quantity;
        });

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $object): void {
            $this->persisted[] = $object;
        });

        return new YackDemoOrderFixture(
            $entityManager,
            $channels,
            $customers,
            $variants,
            $paymentMethods,
            $shippingMethods,
            self::factory(static fn (): Order => new Order()),
            self::factory(static fn (): OrderItem => new OrderItem()),
            self::factory(static fn (): Address => new Address()),
            $quantities,
            $this->stateMachine,
            $kinds,
            new MockClock(self::NOW),
            'YACK_WEB',
            'fr_FR',
            $cache ?? $this->createStub(CacheItemPoolInterface::class),
        );
    }

    /**
     * @template T of object
     *
     * @param \Closure(): T $create
     *
     * @return FactoryInterface<T>
     */
    private static function factory(\Closure $create): FactoryInterface
    {
        return new class($create) implements FactoryInterface {
            public function __construct(private readonly \Closure $create)
            {
            }

            public function createNew(): object
            {
                return ($this->create)();
            }
        };
    }

    private static function customer(string $email, string $group, ?string $company, int $daysAgo, bool $withAddress): Customer
    {
        $customerGroup = new CustomerGroup();
        $customerGroup->setCode($group);
        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setEmailCanonical($email);
        $customer->setFirstName('Prénom');
        $customer->setLastName('Nom');
        $customer->setGroup($customerGroup);
        $customer->setCompany($company);
        $customer->setCreatedAt((new \DateTime(self::NOW))->modify(sprintf('-%d days', $daysAgo)));
        if ($withAddress) {
            $address = new Address();
            $address->setFirstName('Prénom');
            $address->setLastName('Nom');
            $address->setCompany($company);
            $address->setStreet('10 rue du Soleil');
            $address->setPostcode('13100');
            $address->setCity('Aix-en-Provence');
            $address->setCountryCode('FR');
            $customer->setDefaultAddress($address);
        }

        return $customer;
    }

    private static function paymentMethod(string $code): PaymentMethod
    {
        $method = new PaymentMethod();
        $method->setCode($code);

        return $method;
    }

    private static function shippingMethod(string $code): ShippingMethod
    {
        $method = new ShippingMethod();
        $method->setCode($code);

        return $method;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function options(YackDemoOrderFixture $fixture, array $options): array
    {
        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration($fixture, [$options + [
            'payment_methods' => [
                'virement_15j' => ['weight' => 45, 'paid_after_days' => [3, 15]],
                'carte' => ['weight' => 30, 'paid_after_days' => [0, 0], 'individuals' => true],
                'virement_echeance' => ['weight' => 25, 'paid_after_days' => [26, 32]],
            ],
            'shipping_methods' => [
                'yack_transport' => ['weight' => 75, 'shipped_after_days' => [1, 3], 'tracking' => true, 'individuals' => true],
                'yack_agence' => ['weight' => 25, 'shipped_after_days' => [0, 1], 'tracking' => false],
            ],
        ]]);

        return $processed;
    }
}
