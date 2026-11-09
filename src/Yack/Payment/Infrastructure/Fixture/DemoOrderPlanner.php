<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Fixture;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Decides the demo orders, without any database: customers, dates, lines, payment and shipping
 * methods, and what happened to each order. Seeded generators: the same settings, customers,
 * products and loading day give the same orders. Dates are relative to "now" and every status is
 * decided on whole days (order placed N days ago, paid after P days, shipped after S days), so the
 * statuses do not depend on the hour of the load.
 *
 * Seasonality: the day of an order is drawn with the weight of its calendar month (more air
 * conditioning in spring and summer). The days come from their own generator, so the rest of the
 * draw (customers, lines, methods) is the same whatever the loading day.
 *
 * @phpstan-type PaymentSettings array{weight: int, paid_after_days: list<int>, individuals: bool}
 * @phpstan-type ShippingSettings array{weight: int, shipped_after_days: list<int>, tracking: bool, individuals: bool}
 * @phpstan-type Settings array{
 *     seed: int, count: int, days: int, season: non-empty-list<int>,
 *     payment_methods: array<string, PaymentSettings>, shipping_methods: array<string, ShippingSettings>,
 *     cancelled_percent: int, refunded_percent: int, overdue_percent: int, notes: list<string>
 * }
 */
final class DemoOrderPlanner
{
    /** Business hours of the orders of a company, individuals: in minutes after midnight. */
    private const BUSINESS_HOURS = [450, 1110];

    private const INDIVIDUAL_HOURS = [480, 1320];

    /** No order line above this amount (cents, excl. tax): quantities shrink on expensive products. */
    private const LINE_CAP = 1_500_000;

    /** Individuals buy an affordable finished product (cents, excl. tax) when there is one. */
    private const INDIVIDUAL_PRICE_CAP = 250_000;

    /** A refund comes within this many days after the payment (supplier shortage noticed at preparation). */
    public const REFUND_WITHIN_DAYS = 10;

    private Randomizer $random;

    private Randomizer $calendar;

    /** @param Settings $settings */
    public function __construct(private readonly array $settings)
    {
        if (12 !== \count($settings['season']) || min($settings['season']) < 1) {
            throw new \InvalidArgumentException('The season needs 12 positive monthly weights, January first.');
        }
        $this->random = new Randomizer(new Mt19937($settings['seed']));
        $this->calendar = new Randomizer(new Mt19937($settings['seed'] + 1));
    }

    /**
     * @param list<DemoOrderCustomer> $customers
     * @param list<DemoOrderProduct>  $products
     *
     * @return list<PlannedOrder> oldest first
     */
    public function plan(array $customers, array $products, \DateTimeImmutable $now): array
    {
        if ([] === $products) {
            throw new \InvalidArgumentException('No product can be ordered: load the catalogue first.');
        }
        $finished = array_values(array_filter($products, static fn (DemoOrderProduct $p): bool => !$p->sparePart));
        $spareParts = array_values(array_filter($products, static fn (DemoOrderProduct $p): bool => $p->sparePart));

        $slots = [];
        $weighted = [];
        foreach ($customers as $customer) {
            $maxDaysAgo = $this->maxDaysAgo($customer, $now);
            if ($maxDaysAgo < 1) {
                continue;
            }
            // Every customer who orders online has at least one order.
            $minimum = max($customer->minimumOrders, $customer->weight > 0 ? 1 : 0);
            for ($i = 0; $i < $minimum; ++$i) {
                // The most recent order of a guaranteed history is one or two days old and still in progress.
                $slots[] = 0 === $i && $customer->minimumOrders > 0
                    ? [$customer, $this->random->getInt(1, min(2, $maxDaysAgo)), true]
                    : [$customer, $this->day($maxDaysAgo, $now), false];
            }
            if ($customer->weight > 0) {
                $weighted[] = [$customer, $maxDaysAgo];
            }
        }

        $totalWeight = array_sum(array_map(static fn (array $entry): int => $entry[0]->weight, $weighted));
        for ($i = \count($slots); $i < $this->settings['count'] && $totalWeight > 0; ++$i) {
            $ticket = $this->random->getInt(1, $totalWeight);
            foreach ($weighted as [$customer, $maxDaysAgo]) {
                $ticket -= $customer->weight;
                if ($ticket <= 0) {
                    $slots[] = [$customer, $this->day($maxDaysAgo, $now), false];

                    break;
                }
            }
        }

        $orders = [];
        foreach ($slots as [$customer, $daysAgo, $inProgress]) {
            $orders[] = $this->order($customer, $daysAgo, $inProgress, $finished, $spareParts, $now);
        }
        usort($orders, static fn (PlannedOrder $a, PlannedOrder $b): int => $a->placedAt <=> $b->placedAt);

        return $orders;
    }

    /**
     * @param list<DemoOrderProduct> $finished
     * @param list<DemoOrderProduct> $spareParts
     */
    private function order(DemoOrderCustomer $customer, int $daysAgo, bool $inProgress, array $finished, array $spareParts, \DateTimeImmutable $now): PlannedOrder
    {
        [$from, $to] = $customer->business ? self::BUSINESS_HOURS : self::INDIVIDUAL_HOURS;
        $placedAt = $now->setTime(0, 0)->modify(sprintf('-%d days +%d minutes', $daysAgo, $this->random->getInt($from, $to)));
        $lines = $customer->business ? $this->businessLines($finished, $spareParts) : $this->individualLines($finished, $spareParts);

        $paymentMethod = $this->pick($this->settings['payment_methods'], $customer->business);
        $payment = $this->settings['payment_methods'][$paymentMethod];
        $shippingMethod = $this->pick($this->settings['shipping_methods'], $customer->business);
        $shipping = $this->settings['shipping_methods'][$shippingMethod];
        $paidAfter = $this->random->getInt($payment['paid_after_days'][0], $payment['paid_after_days'][1]);
        $shippedAfter = $this->random->getInt($shipping['shipped_after_days'][0], $shipping['shipped_after_days'][1]);
        $immediate = 0 === $payment['paid_after_days'][1];

        // An order in progress is neither cancelled, refunded nor shipped yet.
        $roll = $inProgress ? 101 : $this->random->getInt(1, 100);
        $cancelledBelow = $this->settings['cancelled_percent'];
        $refundedBelow = $cancelledBelow + $this->settings['refunded_percent'];
        $overdueBelow = $refundedBelow + $this->settings['overdue_percent'];

        $notes = $customer->business && [] !== $this->settings['notes'] && $this->random->getInt(1, 100) <= 20
            ? $this->settings['notes'][$this->random->getInt(0, \count($this->settings['notes']) - 1)]
            : null;

        // Cancelled before payment (a card is paid at once: never cancelled this way).
        if (!$immediate && $daysAgo >= 3 && $roll <= $cancelledBelow) {
            $cancelledAt = $this->eventAt($placedAt, $this->random->getInt(0, min(2, $daysAgo - 1)), $now);

            return new PlannedOrder($customer->email, $placedAt, $lines, $paymentMethod, $shippingMethod, null, null, null, $cancelledAt, null, $notes);
        }

        $paidAt = null;
        if ($immediate) {
            $paidAt = $placedAt->modify('+1 minute');
            $paidAfter = 0;
        } elseif ($paidAfter < $daysAgo && !($roll > $refundedBelow && $roll <= $overdueBelow && $daysAgo > $payment['paid_after_days'][1] + 7)) {
            $paidAt = $this->eventAt($placedAt, $paidAfter, $now);
        }

        // Paid, then refunded before shipping (supplier shortage, project dropped), then cancelled.
        if (null !== $paidAt && $roll > $cancelledBelow && $roll <= $refundedBelow && $paidAfter + 1 < $daysAgo) {
            $refundedAt = $this->eventAt($placedAt, $this->random->getInt($paidAfter + 1, min($daysAgo - 1, $paidAfter + self::REFUND_WITHIN_DAYS)), $now);

            return new PlannedOrder($customer->email, $placedAt, $lines, $paymentMethod, $shippingMethod, $paidAt, null, null, $refundedAt, $refundedAt, $notes);
        }

        $shippedAt = !$inProgress && $shippedAfter < $daysAgo ? $this->eventAt($placedAt, $shippedAfter, $now) : null;
        $tracking = null !== $shippedAt && $shipping['tracking'] ? sprintf('YKT%09d', $this->random->getInt(1, 999_999_999)) : null;

        return new PlannedOrder($customer->email, $placedAt, $lines, $paymentMethod, $shippingMethod, $paidAt, $shippedAt, $tracking, null, null, $notes);
    }

    /**
     * Equipment order (one to three finished products, sometimes with parts) or parts order.
     *
     * @param list<DemoOrderProduct> $finished
     * @param list<DemoOrderProduct> $spareParts
     *
     * @return array<string, int>
     */
    private function businessLines(array $finished, array $spareParts): array
    {
        $lines = [];
        if ([] === $spareParts || ([] !== $finished && $this->random->getInt(1, 100) <= 55)) {
            for ($i = $this->random->getInt(1, 3); $i > 0; --$i) {
                $this->add($lines, $this->any($finished), true);
            }
            if ([] !== $spareParts && 1 === $this->random->getInt(1, 2)) {
                for ($i = $this->random->getInt(1, 2); $i > 0; --$i) {
                    $this->add($lines, $this->any($spareParts), true);
                }
            }

            return $lines;
        }

        for ($i = $this->random->getInt(1, 4); $i > 0; --$i) {
            $this->add($lines, $this->any($spareParts), true);
        }

        return $lines;
    }

    /**
     * One affordable finished product, or one or two parts.
     *
     * @param list<DemoOrderProduct> $finished
     * @param list<DemoOrderProduct> $spareParts
     *
     * @return array<string, int>
     */
    private function individualLines(array $finished, array $spareParts): array
    {
        $lines = [];
        if ([] === $finished || ([] !== $spareParts && $this->random->getInt(1, 100) <= 40)) {
            for ($i = $this->random->getInt(1, 2); $i > 0; --$i) {
                $this->add($lines, $this->any($spareParts), false);
            }

            return $lines;
        }

        $affordable = array_values(array_filter($finished, static fn (DemoOrderProduct $p): bool => $p->unitPrice <= self::INDIVIDUAL_PRICE_CAP));
        $this->add($lines, $this->any([] !== $affordable ? $affordable : $finished), false);

        return $lines;
    }

    /** @param array<string, int> $lines */
    private function add(array &$lines, DemoOrderProduct $product, bool $business): void
    {
        $quantity = ($lines[$product->variantCode] ?? 0) + $this->quantity($product->unitPrice, $business);
        // The same product drawn twice: one line, still within the cap.
        $lines[$product->variantCode] = max(1, min($quantity, intdiv(self::LINE_CAP, max(1, $product->unitPrice))));
    }

    /** B2B quantities: a few units of equipment, more of the small parts and consumables. */
    private function quantity(int $unitPrice, bool $business): int
    {
        if (!$business) {
            return $unitPrice < 5_000 ? $this->random->getInt(1, 2) : 1;
        }

        $quantity = match (true) {
            $unitPrice >= 300_000 => $this->random->getInt(1, 2),
            $unitPrice >= 100_000 => $this->random->getInt(1, 4),
            $unitPrice >= 30_000 => $this->random->getInt(1, 6),
            $unitPrice >= 5_000 => $this->random->getInt(1, 8),
            default => $this->random->getInt(2, 12),
        };

        return $quantity;
    }

    /** @param list<DemoOrderProduct> $products */
    private function any(array $products): DemoOrderProduct
    {
        if ([] === $products) {
            throw new \LogicException('Unreachable: the lines are drawn from a non-empty list.');
        }

        return $products[$this->random->getInt(0, \count($products) - 1)];
    }

    /**
     * Weighted choice among the methods open to this kind of customer: its code.
     *
     * @param array<string, array{weight: int, individuals: bool}> $methods
     */
    private function pick(array $methods, bool $business): string
    {
        $open = array_filter($methods, static fn (array $method): bool => $method['weight'] > 0 && ($business || $method['individuals']));
        if ([] === $open) {
            throw new \InvalidArgumentException(sprintf('No method with a positive weight is open to %s.', $business ? 'companies' : 'individuals'));
        }

        $ticket = $this->random->getInt(1, array_sum(array_column($open, 'weight')));
        foreach ($open as $code => $method) {
            $ticket -= $method['weight'];
            if ($ticket <= 0) {
                return $code;
            }
        }

        throw new \LogicException('Unreachable: the ticket is within the total weight.');
    }

    /**
     * Date of an event N whole days after the order (N smaller than the age of the order, so it is
     * in the past): the same day a few hours later, otherwise during the opening hours of that day.
     */
    private function eventAt(\DateTimeImmutable $placedAt, int $daysAfter, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $at = 0 === $daysAfter
            ? $placedAt->modify(sprintf('+%d minutes', $this->random->getInt(45, 240)))
            : $placedAt->setTime(0, 0)->modify(sprintf('+%d days +%d minutes', $daysAfter, $this->random->getInt(510, 1050)));

        return $at < $now ? $at : $now->modify('-1 minute');
    }

    /** Days ago of an order, drawn with the weight of its calendar month (rejection sampling). */
    private function day(int $maxDaysAgo, \DateTimeImmutable $now): int
    {
        $heaviest = max($this->settings['season']);
        do {
            $daysAgo = $this->calendar->getInt(1, $maxDaysAgo);
            $month = (int) $now->modify(sprintf('-%d days', $daysAgo))->format('n');
        } while ($this->calendar->getInt(1, $heaviest) > $this->settings['season'][$month - 1]);

        return $daysAgo;
    }

    /** Orders start the day after the registration and stay within the window of the settings. */
    private function maxDaysAgo(DemoOrderCustomer $customer, \DateTimeImmutable $now): int
    {
        $sinceRegistration = (int) $customer->registeredAt->setTime(0, 0)->diff($now->setTime(0, 0))->format('%r%a');

        return min($this->settings['days'], $sinceRegistration - 1);
    }
}
