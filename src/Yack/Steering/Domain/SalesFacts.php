<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * Port: read-only facts of the shop (Sylius orders, payments, shipments, stock, taxons) the direction pages are
 * computed from. Each method is one query; amounts in cents.
 */
interface SalesFacts
{
    /**
     * Lines of the orders placed in [from, to[ and not cancelled.
     *
     * @return list<OrderLine>
     */
    public function orderLines(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * Every taxon of the two catalogue branches, with its range (the taxon of the first level under the root).
     *
     * @return array<int, TaxonRange> taxon id => range
     */
    public function ranges(string $finishedRootCode, string $partsRootCode): array;

    /**
     * Placed orders (not cancelled) whose payment is still awaited, paid by one of these methods.
     *
     * @param list<string> $paymentMethodCodes
     *
     * @return list<UnpaidOrder>
     */
    public function unpaidOrders(array $paymentMethodCodes): array;

    /**
     * Placed orders (not cancelled) not shipped yet, the oldest first.
     *
     * @return list<OrderToShip>
     */
    public function ordersToShip(): array;

    /** Enabled tracked variants whose available stock (on hand minus on hold) is under the threshold, the lowest first. */
    public function lowStock(int $threshold, int $limit): LowStock;

    /**
     * Spare parts (products of the parts branch) the most ordered in [from, to[, by quantity.
     *
     * @return list<PartDemand>
     */
    public function mostOrderedParts(string $partsRootCode, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array;
}
