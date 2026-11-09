<?php

declare(strict_types=1);

namespace App\Yack\Steering\Application;

use App\Entity\Account\OrderAccessRequest;
use App\Yack\Account\Application\PendingOrderAccessRequestsInterface;
use App\Yack\AfterSales\Application\WarrantyCalendarInterface;
use App\Yack\Steering\Domain\Alert;
use App\Yack\Steering\Domain\AlertKind;
use App\Yack\Steering\Domain\Alerts;
use App\Yack\Steering\Domain\LowStock;
use App\Yack\Steering\Domain\OrderToShip;
use App\Yack\Steering\Domain\Receivable;
use App\Yack\Steering\Domain\SalesFacts;
use App\Yack\Steering\Domain\Wording;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Alerts of the pilotage, ranked by severity:
 * - critique : bank transfers not paid after their échéance (by customer) ; orders paid more than 24 hours ago and
 *   not shipped ;
 * - à surveiller : tracked variants under the stock threshold (app.yack.stock_alert_threshold) ; PRO requests to
 *   open online ordering waiting for a decision ;
 * - pour info : warranties of the installed base ending within 30 days.
 * Read once per request (five queries), kept for the menu badges and the page; reset between requests.
 */
final class AlertBoard implements ResetInterface
{
    /** Lowest variants kept for the « SAV et pièces » page. */
    public const LOW_STOCK_LISTED = 8;

    private ?SteeringSnapshot $snapshot = null;

    public function __construct(
        private readonly SalesFacts $facts,
        private readonly OpenReceivables $receivables,
        private readonly PendingOrderAccessRequestsInterface $pendingRequests,
        private readonly WarrantyCalendarInterface $warranties,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.yack.stock_alert_threshold')]
        private readonly int $stockThreshold,
    ) {
        if ($stockThreshold < 1) {
            throw new \InvalidArgumentException('The stock alert threshold must be at least 1.');
        }
    }

    public function snapshot(): SteeringSnapshot
    {
        if (null !== $this->snapshot) {
            return $this->snapshot;
        }

        $now = $this->clock->now();
        $receivables = $this->receivables->all();
        $ordersToShip = $this->facts->ordersToShip();
        $lowStock = $this->facts->lowStock($this->stockThreshold, self::LOW_STOCK_LISTED);
        $pending = $this->pendingRequests->pending();
        $warranties = $this->warranties->endingBetween($now->setTime(0, 0), $now->setTime(23, 59, 59)->modify('+30 days'));

        return $this->snapshot = new SteeringSnapshot(
            $now,
            $this->stockThreshold,
            $receivables,
            $ordersToShip,
            $lowStock,
            $pending,
            $warranties,
            new Alerts([
                self::overduePayments($receivables, $now),
                self::paidNotShipped($ordersToShip, $now),
                self::lowStock($lowStock, $this->stockThreshold),
                self::pendingRequests($pending, $now),
                new Alert(
                    AlertKind::ExpiringWarranties,
                    \count($warranties),
                    Wording::count(\count($warranties), 'garantie expire', 'garanties expirent').' sous 30 jours',
                    'Occasion de proposer une extension (dates simulées)',
                ),
            ]),
        );
    }

    public function alerts(): Alerts
    {
        return $this->snapshot()->alerts;
    }

    public function reset(): void
    {
        $this->snapshot = null;
    }

    /** @param list<Receivable> $receivables */
    public static function overduePayments(array $receivables, \DateTimeImmutable $now): Alert
    {
        $late = array_values(array_filter($receivables, static fn (Receivable $r): bool => $r->isLateAt($now)));
        $customers = \count(array_unique(array_map(static fn (Receivable $r): int => $r->customerId, $late)));
        $amount = array_sum(array_map(static fn (Receivable $r): int => $r->amount, $late));
        $maxDays = [] === $late ? 0 : max(array_map(static fn (Receivable $r): int => $r->daysLateAt($now), $late));

        return new Alert(
            AlertKind::OverduePayments,
            $customers,
            Wording::count($customers, 'client', 'clients').' en retard de paiement',
            sprintf('%s échus, jusqu\'à %s de retard', Wording::euros($amount), Wording::count($maxDays, 'jour', 'jours')),
        );
    }

    /** @param list<OrderToShip> $ordersToShip */
    public static function paidNotShipped(array $ordersToShip, \DateTimeImmutable $now): Alert
    {
        $late = array_values(array_filter($ordersToShip, static fn (OrderToShip $o): bool => $o->isLateAt($now)));
        $count = \count($late);
        $detail = 1 === $count
            ? sprintf('n° %s, %s', $late[0]->orderNumber, $late[0]->customerLabel)
            : implode(', ', array_map(static fn (OrderToShip $o): string => 'n° '.$o->orderNumber, \array_slice($late, 0, 3))).($count > 3 ? sprintf(' et %d autres', $count - 3) : '');

        return new Alert(
            AlertKind::PaidNotShipped,
            $count,
            Wording::count($count, 'commande payée', 'commandes payées').' non expédiée'.($count > 1 ? 's' : '').' depuis plus de 24 h',
            $detail,
        );
    }

    public static function lowStock(LowStock $lowStock, int $threshold): Alert
    {
        return new Alert(
            AlertKind::LowStock,
            $lowStock->count,
            Wording::count($lowStock->count, 'référence', 'références').' sous le seuil de stock',
            sprintf('dont %d en rupture, seuil : %d disponibles', $lowStock->outOfStock, $threshold),
        );
    }

    /** @param list<OrderAccessRequest> $pending */
    public static function pendingRequests(array $pending, \DateTimeImmutable $now): Alert
    {
        $oldest = [] === $pending ? null : min(array_map(static fn (OrderAccessRequest $r): \DateTimeImmutable => $r->getCreatedAt(), $pending));

        return new Alert(
            AlertKind::PendingOrderAccess,
            \count($pending),
            Wording::count(\count($pending), 'demande PRO', 'demandes PRO').' d\'ouverture à la commande à valider',
            null === $oldest ? '' : 'la plus ancienne : '.Wording::daysAgo($oldest, $now),
        );
    }
}
