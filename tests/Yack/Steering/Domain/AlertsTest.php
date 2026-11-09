<?php

declare(strict_types=1);

namespace App\Tests\Yack\Steering\Domain;

use App\Yack\Steering\Domain\Alert;
use App\Yack\Steering\Domain\AlertKind;
use App\Yack\Steering\Domain\Alerts;
use App\Yack\Steering\Domain\AlertSeverity;
use App\Yack\Steering\Domain\SteeringPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Alerts::class)]
#[CoversClass(AlertKind::class)]
#[CoversClass(AlertSeverity::class)]
final class AlertsTest extends TestCase
{
    public function testEachKindHasItsSeverity(): void
    {
        $this->assertSame(AlertSeverity::Critical, AlertKind::OverduePayments->severity());
        $this->assertSame(AlertSeverity::Critical, AlertKind::PaidNotShipped->severity());
        $this->assertSame(AlertSeverity::Watch, AlertKind::LowStock->severity());
        $this->assertSame(AlertSeverity::Watch, AlertKind::PendingOrderAccess->severity());
        $this->assertSame(AlertSeverity::Info, AlertKind::ExpiringWarranties->severity());
    }

    public function testRankedBySeverityWithoutTheEmptyOnes(): void
    {
        $alerts = self::alerts(['garanties_30_jours' => 3, 'stock_sous_seuil' => 85, 'retards_paiement' => 0, 'payee_non_expediee' => 1]);

        $this->assertSame(['payee_non_expediee', 'stock_sous_seuil', 'garanties_30_jours'], array_map(static fn (Alert $a): string => $a->kind->value, $alerts->all));
        $this->assertSame(['Critique', 'À surveiller', 'Pour info'], array_map(static fn (array $g): string => $g['severity']->label(), $alerts->bySeverity()));
        $this->assertNull($alerts->get(AlertKind::OverduePayments));
    }

    public function testTheBadgeCountsTheCriticalAndWatchLinesOfTheTabAndTheMenuTheirTotal(): void
    {
        $alerts = self::alerts(['retards_paiement' => 3, 'payee_non_expediee' => 1, 'stock_sous_seuil' => 85, 'demandes_ouverture' => 2, 'garanties_30_jours' => 4]);

        $this->assertSame(1, $alerts->badge(SteeringPage::Pilotage));
        $this->assertSame(4, $alerts->total());
        $this->assertSame(1, $alerts->badge(SteeringPage::AfterSales));
        $this->assertSame(2, $alerts->badge(SteeringPage::Customers));
        $this->assertSame(2, $alerts->count(AlertSeverity::Critical));
    }

    public function testNoAlertNoBadge(): void
    {
        $alerts = self::alerts(['garanties_30_jours' => 5]);

        $this->assertSame(0, $alerts->badge(SteeringPage::AfterSales));
        $this->assertSame(0, $alerts->total());
    }

    /** @param array<string, int> $counts */
    private static function alerts(array $counts): Alerts
    {
        $list = [];
        foreach ($counts as $kind => $count) {
            $list[] = new Alert(AlertKind::from($kind), $count, $kind, '');
        }

        return new Alerts($list);
    }
}
