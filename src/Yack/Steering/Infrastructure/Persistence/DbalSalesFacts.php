<?php

declare(strict_types=1);

namespace App\Yack\Steering\Infrastructure\Persistence;

use App\Yack\Steering\Domain\LowStock;
use App\Yack\Steering\Domain\OrderLine;
use App\Yack\Steering\Domain\OrderToShip;
use App\Yack\Steering\Domain\PartDemand;
use App\Yack\Steering\Domain\SalesFacts;
use App\Yack\Steering\Domain\StockedVariant;
use App\Yack\Steering\Domain\TaxonRange;
use App\Yack\Steering\Domain\UnpaidOrder;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Read-only SQL on the Sylius tables (orders, adjustments, payments, shipments, variants, taxons): one query per
 * method, aggregated in the database where it can, portable between MySQL and SQLite (dates passed as parameters, no
 * date function). Indexes used: the foreign keys of the order items, units, adjustments and payments, the order
 * state and the checkout date filter on sylius_order.
 */
final readonly class DbalSalesFacts implements SalesFacts
{
    private const DATE = 'Y-m-d H:i:s';

    /** Last payment of an order (Sylius keeps the cancelled ones when the customer changes method). */
    private const LAST_PAYMENT = 'pay.id = (SELECT MAX(p2.id) FROM sylius_payment p2 WHERE p2.order_id = o.id)';

    private const CUSTOMER = 'c.company AS customer_company, c.first_name AS customer_first_name, c.last_name AS customer_last_name, c.email AS customer_email';

    public function __construct(
        private Connection $connection,
        #[Autowire(param: 'app.yack.locale')]
        private string $locale,
    ) {
    }

    public function orderLines(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Line amount excl. tax = line total (units, their escompte and promotions, VAT included when not neutral) minus
        // the VAT of its units. The derived table reads the adjustments of the period only.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id AS order_id, o.checkout_completed_at AS ordered_at, pm.code AS method_code, pmt.name AS method_name,
                    p.main_taxon_id AS taxon_id, i.total AS line_total, COALESCE(x.tax, 0) AS tax, COALESCE(x.escompte, 0) AS escompte
             FROM sylius_order_item i
             JOIN sylius_order o ON o.id = i.order_id
             JOIN sylius_product_variant v ON v.id = i.variant_id
             JOIN sylius_product p ON p.id = v.product_id
             LEFT JOIN (
                 SELECT u.order_item_id,
                        SUM(CASE WHEN a.type = :tax AND a.is_neutral = :no THEN a.amount ELSE 0 END) AS tax,
                        SUM(CASE WHEN a.type = :escompte THEN -a.amount ELSE 0 END) AS escompte
                 FROM sylius_adjustment a
                 JOIN sylius_order_item_unit u ON u.id = a.order_item_unit_id
                 JOIN sylius_order_item i2 ON i2.id = u.order_item_id
                 JOIN sylius_order o2 ON o2.id = i2.order_id
                 WHERE o2.checkout_completed_at >= :from AND o2.checkout_completed_at < :to
                 GROUP BY u.order_item_id
             ) x ON x.order_item_id = i.id
             LEFT JOIN sylius_payment pay ON '.self::LAST_PAYMENT.'
             LEFT JOIN sylius_payment_method pm ON pm.id = pay.method_id
             LEFT JOIN sylius_payment_method_translation pmt ON pmt.translatable_id = pm.id AND pmt.locale = :locale
             WHERE o.checkout_completed_at >= :from AND o.checkout_completed_at < :to AND o.state <> :cancelled
             ORDER BY o.checkout_completed_at, i.id',
            ['from' => $from->format(self::DATE), 'to' => $to->format(self::DATE), 'cancelled' => 'cancelled', 'tax' => 'tax', 'escompte' => 'escompte', 'no' => false, 'locale' => $this->locale],
            ['no' => ParameterType::BOOLEAN],
        );

        return array_map(static fn (array $row): OrderLine => new OrderLine(
            self::int($row['order_id']),
            self::date($row['ordered_at']),
            self::stringOrNull($row['method_code']),
            self::stringOrNull($row['method_name']),
            null === $row['taxon_id'] ? null : self::int($row['taxon_id']),
            self::int($row['line_total']) - self::int($row['tax']),
            self::int($row['escompte']),
        ), $rows);
    }

    public function ranges(string $finishedRootCode, string $partsRootCode): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.id AS taxon_id, r.code AS root_code, g.code AS range_code, COALESCE(gt.name, g.code) AS range_name
             FROM sylius_taxon r
             JOIN sylius_taxon g ON g.parent_id = r.id
             JOIN sylius_taxon t ON t.tree_root = g.tree_root AND t.tree_left >= g.tree_left AND t.tree_right <= g.tree_right
             LEFT JOIN sylius_taxon_translation gt ON gt.translatable_id = g.id AND gt.locale = :locale
             WHERE r.code IN (:roots)',
            ['roots' => [$finishedRootCode, $partsRootCode], 'locale' => $this->locale],
            ['roots' => ArrayParameterType::STRING],
        );

        $ranges = [];
        foreach ($rows as $row) {
            $ranges[self::int($row['taxon_id'])] = new TaxonRange(self::string($row['root_code']), self::string($row['range_code']), self::string($row['range_name']));
        }

        return $ranges;
    }

    public function unpaidOrders(array $paymentMethodCodes): array
    {
        if ([] === $paymentMethodCodes) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id, o.number, o.total, o.checkout_completed_at, c.id AS customer_id, '.self::CUSTOMER.', pm.code AS method_code
             FROM sylius_order o
             JOIN sylius_customer c ON c.id = o.customer_id
             JOIN sylius_payment pay ON '.self::LAST_PAYMENT.'
             JOIN sylius_payment_method pm ON pm.id = pay.method_id
             WHERE o.state = :new AND o.payment_state = :awaiting AND o.checkout_completed_at IS NOT NULL AND pm.code IN (:methods)
             ORDER BY o.checkout_completed_at, o.id',
            ['new' => 'new', 'awaiting' => 'awaiting_payment', 'methods' => $paymentMethodCodes],
            ['methods' => ArrayParameterType::STRING],
        );

        return array_map(static fn (array $row): UnpaidOrder => new UnpaidOrder(
            self::int($row['id']),
            self::string($row['number']),
            self::int($row['customer_id']),
            self::customerLabel($row),
            self::int($row['total']),
            self::date($row['checkout_completed_at']),
            self::string($row['method_code']),
        ), $rows);
    }

    public function ordersToShip(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id, o.number, o.checkout_completed_at, '.self::CUSTOMER.',
                    (SELECT MAX(COALESCE(p.updated_at, p.created_at)) FROM sylius_payment p WHERE p.order_id = o.id AND p.state = :completed) AS paid_at
             FROM sylius_order o
             JOIN sylius_customer c ON c.id = o.customer_id
             WHERE o.state = :new AND o.shipping_state IN (:shipping) AND o.checkout_completed_at IS NOT NULL
             ORDER BY o.checkout_completed_at, o.id',
            ['new' => 'new', 'completed' => 'completed', 'shipping' => ['ready', 'partially_shipped']],
            ['shipping' => ArrayParameterType::STRING],
        );

        return array_map(static fn (array $row): OrderToShip => new OrderToShip(
            self::int($row['id']),
            self::string($row['number']),
            self::customerLabel($row),
            self::date($row['checkout_completed_at']),
            null === $row['paid_at'] ? null : self::date($row['paid_at']),
        ), $rows);
    }

    public function lowStock(int $threshold, int $limit): LowStock
    {
        // Every variant under the threshold (the count and the share out of stock), the list cut in PHP.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT v.product_id, v.code, COALESCE(pt.name, v.code) AS name, v.on_hand - v.on_hold AS available
             FROM sylius_product_variant v
             JOIN sylius_product p ON p.id = v.product_id
             LEFT JOIN sylius_product_translation pt ON pt.translatable_id = p.id AND pt.locale = :locale
             WHERE v.tracked = :yes AND v.enabled = :yes AND p.enabled = :yes AND v.on_hand - v.on_hold < :threshold
             ORDER BY available, v.code',
            ['yes' => true, 'threshold' => $threshold, 'locale' => $this->locale],
            ['yes' => ParameterType::BOOLEAN, 'threshold' => ParameterType::INTEGER],
        );

        $variants = array_map(static fn (array $row): StockedVariant => new StockedVariant(
            self::int($row['product_id']),
            self::string($row['code']),
            self::string($row['name']),
            max(0, self::int($row['available'])),
        ), $rows);

        return new LowStock(
            \count($variants),
            \count(array_filter($variants, static fn (StockedVariant $v): bool => $v->available <= 0)),
            \array_slice($variants, 0, max(0, $limit)),
        );
    }

    public function mostOrderedParts(string $partsRootCode, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.id AS product_id, v.code, COALESCE(pt.name, v.code) AS name, SUM(i.quantity) AS quantity, COUNT(DISTINCT o.id) AS orders,
                    v.tracked, v.on_hand - v.on_hold AS available
             FROM sylius_order_item i
             JOIN sylius_order o ON o.id = i.order_id
             JOIN sylius_product_variant v ON v.id = i.variant_id
             JOIN sylius_product p ON p.id = v.product_id
             JOIN sylius_taxon t ON t.id = p.main_taxon_id
             JOIN sylius_taxon r ON r.code = :root AND t.tree_root = r.tree_root AND t.tree_left >= r.tree_left AND t.tree_right <= r.tree_right
             LEFT JOIN sylius_product_translation pt ON pt.translatable_id = p.id AND pt.locale = :locale
             WHERE o.checkout_completed_at >= :from AND o.checkout_completed_at < :to AND o.state <> :cancelled
             GROUP BY p.id, v.id, v.code, pt.name, v.tracked, v.on_hand, v.on_hold
             ORDER BY quantity DESC, orders DESC, v.code
             LIMIT '.max(1, $limit),
            ['root' => $partsRootCode, 'from' => $from->format(self::DATE), 'to' => $to->format(self::DATE), 'cancelled' => 'cancelled', 'locale' => $this->locale],
        );

        return array_map(static fn (array $row): PartDemand => new PartDemand(
            self::int($row['product_id']),
            self::string($row['code']),
            self::string($row['name']),
            self::int($row['quantity']),
            self::int($row['orders']),
            (bool) $row['tracked'],
            max(0, self::int($row['available'])),
        ), $rows);
    }

    /**
     * Name shown for a customer: company, otherwise first and last names, otherwise e-mail.
     *
     * @param array<string, mixed> $row
     */
    private static function customerLabel(array $row): string
    {
        $company = trim(self::string($row['customer_company']));
        $name = trim(self::string($row['customer_first_name']).' '.self::string($row['customer_last_name']));

        return '' !== $company ? $company : ('' !== $name ? $name : self::string($row['customer_email']));
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function string(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    private static function date(mixed $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::string($value));
    }
}
