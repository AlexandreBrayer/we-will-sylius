<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * The alerts of the moment, without the empty ones, ranked by severity (critique, à surveiller, pour info).
 *
 * Badge of a dashboard tab = number of its alerts « critique » and « à surveiller » (a line, not the number of cases:
 * 85 variants under the threshold make one alert). The badge of « Tableau de bord » in the menu is their total, the
 * sum of the three tabs.
 */
final readonly class Alerts
{
    /** @var list<Alert> */
    public array $all;

    /** @param list<Alert> $alerts */
    public function __construct(array $alerts)
    {
        $kept = array_values(array_filter($alerts, static fn (Alert $alert): bool => $alert->count > 0));
        $rank = array_flip(array_map(static fn (AlertSeverity $s): string => $s->value, AlertSeverity::cases()));
        $order = array_flip(array_map(static fn (AlertKind $k): string => $k->value, AlertKind::cases()));
        usort($kept, static fn (Alert $a, Alert $b): int => [$rank[$a->severity()->value], $order[$a->kind->value]] <=> [$rank[$b->severity()->value], $order[$b->kind->value]]);
        $this->all = $kept;
    }

    /**
     * Severity groups in display order, the empty ones left out.
     *
     * @return list<array{severity: AlertSeverity, alerts: non-empty-list<Alert>}>
     */
    public function bySeverity(): array
    {
        $groups = [];
        foreach (AlertSeverity::cases() as $severity) {
            $alerts = array_values(array_filter($this->all, static fn (Alert $alert): bool => $alert->severity() === $severity));
            if ([] !== $alerts) {
                $groups[] = ['severity' => $severity, 'alerts' => $alerts];
            }
        }

        return $groups;
    }

    public function badge(SteeringPage $page): int
    {
        return \count(array_filter($this->all, static fn (Alert $alert): bool => $alert->severity()->needsAction() && $alert->kind->page() === $page));
    }

    public function total(): int
    {
        return \count(array_filter($this->all, static fn (Alert $alert): bool => $alert->severity()->needsAction()));
    }

    public function count(AlertSeverity $severity): int
    {
        return \count(array_filter($this->all, static fn (Alert $alert): bool => $alert->severity() === $severity));
    }

    public function get(AlertKind $kind): ?Alert
    {
        foreach ($this->all as $alert) {
            if ($alert->kind === $kind) {
                return $alert;
            }
        }

        return null;
    }
}
