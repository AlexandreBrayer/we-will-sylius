<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * The alerts of the pilotage: each one has a fixed severity and belongs to the page that lists its cases.
 */
enum AlertKind: string
{
    /** Bank transfer not paid after its échéance. */
    case OverduePayments = 'retards_paiement';
    /** Order paid more than 24 hours ago and not shipped yet. */
    case PaidNotShipped = 'payee_non_expediee';
    /** Tracked variants whose available stock is under the threshold (app.yack.stock_alert_threshold). */
    case LowStock = 'stock_sous_seuil';
    /** PRO requests to open online ordering, waiting for a decision. */
    case PendingOrderAccess = 'demandes_ouverture';
    /** Warranties of the installed base ending within 30 days. */
    case ExpiringWarranties = 'garanties_30_jours';

    public function severity(): AlertSeverity
    {
        return match ($this) {
            self::OverduePayments, self::PaidNotShipped => AlertSeverity::Critical,
            self::LowStock, self::PendingOrderAccess => AlertSeverity::Watch,
            self::ExpiringWarranties => AlertSeverity::Info,
        };
    }

    public function page(): SteeringPage
    {
        return match ($this) {
            self::OverduePayments, self::PendingOrderAccess => SteeringPage::Customers,
            self::LowStock, self::ExpiringWarranties => SteeringPage::AfterSales,
            self::PaidNotShipped => SteeringPage::Pilotage,
        };
    }
}
