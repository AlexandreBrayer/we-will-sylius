<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Fixture;

use App\Yack\Payment\Infrastructure\Fixture\DemoOrderCustomer;
use App\Yack\Payment\Infrastructure\Fixture\DemoOrderPlanner;
use App\Yack\Payment\Infrastructure\Fixture\DemoOrderProduct;
use App\Yack\Payment\Infrastructure\Fixture\PlannedOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DemoOrderPlanner::class)]
final class DemoOrderPlannerTest extends TestCase
{
    private const NOW = '2026-11-09 10:30:00';

    public function testTheSameSettingsGiveTheSameOrders(): void
    {
        $first = (new DemoOrderPlanner(self::settings()))->plan(self::customers(), self::products(), self::now());
        $second = (new DemoOrderPlanner(self::settings()))->plan(self::customers(), self::products(), self::now()->setTime(22, 0));

        $this->assertSame(self::describe($first), self::describe($second));
        $this->assertNotSame(self::describe($first), self::describe((new DemoOrderPlanner(['seed' => 7] + self::settings()))->plan(self::customers(), self::products(), self::now())));
    }

    public function testOrdersStayInTheWindowAfterTheRegistrationAndOldestFirst(): void
    {
        $orders = (new DemoOrderPlanner(self::settings()))->plan(self::customers(), self::products(), self::now());

        $this->assertCount(200, $orders);
        $registered = [];
        foreach (self::customers() as $customer) {
            $registered[$customer->email] = $customer->registeredAt->setTime(0, 0)->modify('+1 day');
        }
        $previous = null;
        foreach ($orders as $order) {
            $this->assertGreaterThanOrEqual(self::now()->setTime(0, 0)->modify('-365 days'), $order->placedAt);
            $this->assertLessThan(self::now()->setTime(0, 0), $order->placedAt, 'Au plus tard la veille du chargement');
            $this->assertGreaterThanOrEqual($registered[$order->customerEmail], $order->placedAt);
            if (null !== $previous) {
                $this->assertGreaterThanOrEqual($previous, $order->placedAt);
            }
            $previous = $order->placedAt;
        }
    }

    public function testAGuaranteedHistoryIsExactAndEndsWithAnOrderInProgress(): void
    {
        $orders = (new DemoOrderPlanner(self::settings()))->plan(self::customers(), self::products(), self::now());

        $history = array_values(array_filter($orders, static fn (PlannedOrder $o): bool => 'demo@yack.test' === $o->customerEmail));
        $this->assertCount(5, $history);
        $latest = end($history);
        $this->assertGreaterThanOrEqual(self::now()->setTime(0, 0)->modify('-2 days'), $latest->placedAt);
        $this->assertNull($latest->shippedAt);
        $this->assertNull($latest->cancelledAt);
        $this->assertNull($latest->refundedAt);
        $this->assertNull($latest->tracking);
    }

    public function testEveryCustomerWhoOrdersHasAnOrderAndOthersNone(): void
    {
        $orders = (new DemoOrderPlanner(['count' => 3] + self::settings()))->plan(self::customers(), self::products(), self::now());

        $emails = array_unique(array_map(static fn (PlannedOrder $o): string => $o->customerEmail, $orders));
        sort($emails);
        $this->assertSame(['company1@exemple.test', 'company2@exemple.test', 'demo@yack.test', 'person@exemple.test'], $emails);
        $this->assertCount(8, $orders, 'Les minimums passent avant le nombre demandé');
    }

    public function testStatusesAndDatesAreConsistent(): void
    {
        $orders = (new DemoOrderPlanner(self::settings()))->plan(self::customers(), self::products(), self::now());
        $prices = [];
        foreach (self::products() as $product) {
            $prices[$product->variantCode] = $product->unitPrice;
        }

        $seen = ['cancelled' => 0, 'refunded' => 0, 'awaiting' => 0, 'ready' => 0, 'shipped' => 0, 'notes' => 0];
        foreach ($orders as $order) {
            $this->assertNotSame([], $order->lines);
            foreach ($order->lines as $code => $quantity) {
                $this->assertGreaterThan(0, $quantity);
                $this->assertTrue(1 === $quantity || $quantity * $prices[$code] <= 1_500_000, 'Ligne plafonnée');
            }
            foreach ([$order->paidAt, $order->shippedAt, $order->cancelledAt, $order->refundedAt] as $date) {
                if (null !== $date) {
                    $this->assertGreaterThan($order->placedAt, $date);
                    $this->assertLessThan(self::now(), $date);
                }
            }
            if ('person@exemple.test' === $order->customerEmail) {
                $this->assertSame(['carte', 'transport'], [$order->paymentMethod, $order->shippingMethod], 'Moyens ouverts aux particuliers');
                $this->assertNull($order->notes);
            }
            if ('carte' === $order->paymentMethod) {
                $this->assertNotNull($order->paidAt, 'Une carte est payée à la commande');
            }
            if (null !== $order->notes) {
                ++$seen['notes'];
            }

            if (null !== $order->refundedAt) {
                ++$seen['refunded'];
                $this->assertNotNull($order->paidAt);
                $this->assertGreaterThan($order->paidAt, $order->refundedAt);
                $this->assertLessThanOrEqual(
                    DemoOrderPlanner::REFUND_WITHIN_DAYS + 1,
                    (int) $order->paidAt->setTime(0, 0)->diff($order->refundedAt->setTime(0, 0))->format('%a'),
                    'Remboursé quelques jours après le paiement, pas des mois plus tard',
                );
                $this->assertSame($order->refundedAt, $order->cancelledAt);
                $this->assertNull($order->shippedAt);
            } elseif (null !== $order->cancelledAt) {
                ++$seen['cancelled'];
                $this->assertNotSame('carte', $order->paymentMethod);
                $this->assertNull($order->paidAt);
                $this->assertNull($order->shippedAt);
            } elseif (null !== $order->shippedAt) {
                ++$seen['shipped'];
                $this->assertSame('transport' === $order->shippingMethod, null !== $order->tracking, 'N° de suivi pour le transporteur seulement');
                if (null !== $order->tracking) {
                    $this->assertMatchesRegularExpression('/^YKT\d{9}$/', $order->tracking);
                }
            } else {
                ++$seen['ready'];
                $this->assertNull($order->tracking);
            }
            if (null === $order->paidAt && null === $order->cancelledAt) {
                ++$seen['awaiting'];
            }
            $this->assertSame(max(array_filter([$order->placedAt, $order->paidAt, $order->shippedAt, $order->cancelledAt])), $order->lastEventAt());
        }

        foreach ($seen as $status => $count) {
            $this->assertGreaterThan(0, $count, $status);
        }
        $this->assertGreaterThan(0.6, $seen['shipped'] / \count($orders), 'La plupart des commandes sont expédiées');
    }

    public function testOverdueTransfersStayUnpaid(): void
    {
        $orders = (new DemoOrderPlanner(['cancelled_percent' => 0, 'refunded_percent' => 0, 'overdue_percent' => 100] + self::settings()))->plan(self::customers(), self::products(), self::now());

        foreach ($orders as $order) {
            $age = (int) $order->placedAt->setTime(0, 0)->diff(self::now()->setTime(0, 0))->format('%a');
            if ('virement' === $order->paymentMethod && $age > 10 + 7) {
                $this->assertNull($order->paidAt);
            }
        }
    }

    public function testTheSeasonWeighsTheMonths(): void
    {
        $season = array_fill(0, 12, 1);
        $season[6] = 100;
        $orders = (new DemoOrderPlanner(['season' => $season] + self::settings()))->plan(self::customers(), self::products(), self::now());

        $months = array_count_values(array_map(static fn (PlannedOrder $o): string => $o->placedAt->format('n'), $orders));
        arsort($months);
        // Minimum orders (one per customer, recent histories) and late registrations aside, July takes the bulk.
        $this->assertSame('7', (string) array_key_first($months));
        $this->assertGreaterThan(0.4, $months[7] / \count($orders));
        foreach ([1, 2, 3, 4, 5] as $month) {
            $this->assertGreaterThan(5 * ($months[$month] ?? 0), $months[7]);
        }
    }

    public function testWithoutSparePartsOrWithoutFinishedProductsTheOrdersStillHaveLines(): void
    {
        foreach ([true, false] as $sparePart) {
            $products = array_values(array_filter(self::products(), static fn (DemoOrderProduct $p): bool => $p->sparePart === $sparePart));
            $orders = (new DemoOrderPlanner(['count' => 20] + self::settings()))->plan(self::customers(), $products, self::now());
            foreach ($orders as $order) {
                $this->assertNotSame([], $order->lines);
            }
        }
    }

    public function testACustomerRegisteredTodayHasNoOrder(): void
    {
        $customers = [new DemoOrderCustomer('new@exemple.test', self::now()->setTime(8, 0), true, 2, 4)];

        $this->assertSame([], (new DemoOrderPlanner(self::settings()))->plan($customers, self::products(), self::now()));
    }

    public function testWithoutProductNothingIsPlanned(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No product can be ordered');

        (new DemoOrderPlanner(self::settings()))->plan(self::customers(), [], self::now());
    }

    public function testAMethodMustBeOpenToIndividuals(): void
    {
        $settings = self::settings();
        $settings['payment_methods']['carte']['individuals'] = false;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('open to individuals');

        (new DemoOrderPlanner($settings))->plan(self::customers(), self::products(), self::now());
    }

    public function testTheSeasonNeedsTwelvePositiveWeights(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DemoOrderPlanner(['season' => [1, 2, 3]] + self::settings());
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    /** @return list<DemoOrderCustomer> */
    private static function customers(): array
    {
        return [
            new DemoOrderCustomer('company1@exemple.test', self::now()->modify('-700 days'), true, 0, 4),
            new DemoOrderCustomer('company2@exemple.test', self::now()->modify('-40 days'), true, 0, 4),
            new DemoOrderCustomer('person@exemple.test', self::now()->modify('-500 days'), false, 0, 1),
            new DemoOrderCustomer('demo@yack.test', self::now()->modify('-600 days'), true, 5, 0),
            new DemoOrderCustomer('pro@exemple.test', self::now()->modify('-600 days'), true, 0, 0),
        ];
    }

    /** @return list<DemoOrderProduct> */
    private static function products(): array
    {
        return [
            new DemoOrderProduct('PAC-16', 984_790, false),
            new DemoOrderProduct('PAC-08', 420_000, false),
            new DemoOrderProduct('UE-35', 150_000, false),
            new DemoOrderProduct('UI-25', 99_090, false),
            new DemoOrderProduct('TELECOMMANDE', 9_490, false),
            new DemoOrderProduct('COMPRESSEUR', 149_000, true),
            new DemoOrderProduct('CARTE', 39_000, true),
            new DemoOrderProduct('SONDE', 2_990, true),
            new DemoOrderProduct('FILTRE', 1_290, true),
        ];
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        return [
            'seed' => 20261108,
            'count' => 200,
            'days' => 365,
            'season' => [6, 7, 9, 11, 14, 17, 18, 13, 10, 8, 7, 6],
            'payment_methods' => [
                'virement' => ['weight' => 50, 'paid_after_days' => [3, 10], 'individuals' => false],
                'carte' => ['weight' => 30, 'paid_after_days' => [0, 0], 'individuals' => true],
                'inactif' => ['weight' => 0, 'paid_after_days' => [1, 1], 'individuals' => true],
            ],
            'shipping_methods' => [
                'transport' => ['weight' => 70, 'shipped_after_days' => [1, 3], 'tracking' => true, 'individuals' => true],
                'agence' => ['weight' => 30, 'shipped_after_days' => [0, 1], 'tracking' => false, 'individuals' => false],
            ],
            'cancelled_percent' => 6,
            'refunded_percent' => 4,
            'overdue_percent' => 5,
            'notes' => ['Livraison sur chantier.', 'Avant 10 h.'],
        ];
    }

    /**
     * @param list<PlannedOrder> $orders
     *
     * @return list<string>
     */
    private static function describe(array $orders): array
    {
        return array_map(static fn (PlannedOrder $o): string => json_encode([
            $o->customerEmail, $o->placedAt->format('c'), $o->lines, $o->paymentMethod, $o->shippingMethod, $o->paidAt?->format('c'),
            $o->shippedAt?->format('c'), $o->tracking, $o->cancelledAt?->format('c'), $o->refundedAt?->format('c'), $o->notes,
        ], \JSON_THROW_ON_ERROR), $orders);
    }
}
