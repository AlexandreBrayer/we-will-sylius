<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Yack\Catalog\Application\CatalogBranchesInterface;
use App\Yack\Payment\Application\PaymentMethodTermsInterface;
use App\Yack\Steering\Domain\OrderLine;
use App\Yack\Steering\Domain\SalesFacts;
use App\Yack\Steering\Domain\TaxonRange;
use App\Yack\Steering\Domain\Variation;

/**
 * Figures of the « Pilotage » page over 30 rolling days, compared with the 30 days before. CA = amount excl. tax of
 * the goods (after discounts and escompte, shipping excluded) of the orders placed in the period and not cancelled.
 * Range = taxon of the first level under the finished products root (Clik&Yack), every spare part in one range
 * « Pièces détachées », the rest in « Autres ».
 */
final readonly class PilotageReport
{
    public const DAYS = 30;

    public const PARTS_RANGE = 'Pièces détachées';

    public const OTHER_RANGE = 'Autres';

    public function __construct(
        private SalesFacts $facts,
        private AlertBoard $alerts,
        private CatalogBranchesInterface $branches,
        private PaymentMethodTermsInterface $paymentTerms,
    ) {
    }

    public function build(): Pilotage
    {
        $snapshot = $this->alerts->snapshot();
        $now = $snapshot->now;
        $start = $now->modify(sprintf('-%d days', self::DAYS));
        $lines = $this->facts->orderLines($now->modify(sprintf('-%d days', 2 * self::DAYS)), $now);
        $current = array_values(array_filter($lines, static fn (OrderLine $l): bool => $l->orderedAt >= $start));
        $previous = array_values(array_filter($lines, static fn (OrderLine $l): bool => $l->orderedAt < $start));

        [$revenue, $orders] = self::totals($current);
        [$previousRevenue, $previousOrders] = self::totals($previous);
        $basket = self::average($revenue, $orders);

        return new Pilotage(
            $snapshot,
            $revenue,
            $previousRevenue,
            Variation::between($revenue, $previousRevenue),
            $orders,
            $previousOrders,
            $basket,
            $orders > 0 ? Variation::between($basket, self::average($previousRevenue, $previousOrders)) : null,
            self::byRange($current, $this->facts->ranges($this->branches->finishedProductsRootCode(), $this->branches->sparePartsRootCode()), $this->branches->sparePartsRootCode()),
            $this->byMethod($current, $revenue),
            array_sum(array_map(static fn (OrderLine $l): int => $l->escompte, $current)),
        );
    }

    /**
     * @param list<OrderLine> $lines
     *
     * @return array{int, int} CA, number of orders
     */
    public static function totals(array $lines): array
    {
        return [
            array_sum(array_map(static fn (OrderLine $l): int => $l->amount, $lines)),
            \count(array_unique(array_map(static fn (OrderLine $l): int => $l->orderId, $lines))),
        ];
    }

    public static function average(int $revenue, int $orders): int
    {
        return $orders > 0 ? (int) round($revenue / $orders) : 0;
    }

    /**
     * @param list<OrderLine>        $lines
     * @param array<int, TaxonRange> $ranges
     *
     * @return list<array{name: string, amount: int, share: int}>
     */
    public static function byRange(array $lines, array $ranges, string $partsRootCode): array
    {
        $amounts = [];
        foreach ($lines as $line) {
            $range = null === $line->taxonId ? null : ($ranges[$line->taxonId] ?? null);
            $name = match (true) {
                null === $range => self::OTHER_RANGE,
                $range->rootCode === $partsRootCode => self::PARTS_RANGE,
                default => $range->name,
            };
            $amounts[$name] = ($amounts[$name] ?? 0) + $line->amount;
        }
        $amounts = array_filter($amounts, static fn (int $amount): bool => 0 !== $amount);
        uksort($amounts, static fn (string $a, string $b): int => [$amounts[$b], $a] <=> [$amounts[$a], $b]);
        $highest = [] === $amounts ? 0 : max($amounts);

        $rows = [];
        foreach ($amounts as $name => $amount) {
            $rows[] = ['name' => (string) $name, 'amount' => $amount, 'share' => $highest > 0 ? max(0, (int) round($amount * 100 / $highest)) : 0];
        }

        return $rows;
    }

    /**
     * @param list<OrderLine> $lines
     *
     * @return list<array{code: string, name: string, amount: int, share: int, orders: int, escompte: int, rate: float|null}>
     */
    private function byMethod(array $lines, int $revenue): array
    {
        $methods = [];
        foreach ($lines as $line) {
            $code = $line->paymentMethodCode ?? '';
            $methods[$code] ??= ['code' => $code, 'name' => $line->paymentMethodName ?? 'Sans moyen de paiement', 'amount' => 0, 'share' => 0, 'orders' => [], 'escompte' => 0, 'rate' => '' === $code ? null : $this->paymentTerms->escompteRate($code)];
            $methods[$code]['amount'] += $line->amount;
            $methods[$code]['escompte'] += $line->escompte;
            $methods[$code]['orders'][$line->orderId] = true;
        }

        $rows = [];
        foreach ($methods as $method) {
            $rows[] = [...$method, 'orders' => \count($method['orders']), 'share' => $revenue > 0 ? (int) round($method['amount'] * 100 / $revenue) : 0];
        }
        usort($rows, static fn (array $a, array $b): int => [$b['amount'], $a['name']] <=> [$a['amount'], $b['name']]);

        return $rows;
    }
}
