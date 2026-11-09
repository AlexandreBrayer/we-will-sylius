<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * One line of the alerts: how many cases, a title (« 2 clients en retard de paiement ») and a detail line.
 */
final readonly class Alert
{
    public function __construct(
        public AlertKind $kind,
        public int $count,
        public string $title,
        public string $detail,
    ) {
        if ($count < 0) {
            throw new \InvalidArgumentException('An alert cannot count fewer than zero cases.');
        }
    }

    public function severity(): AlertSeverity
    {
        return $this->kind->severity();
    }
}
