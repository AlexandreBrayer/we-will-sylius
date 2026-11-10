<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Filters of the journal of the runs: status, source (or the simulations only), period. A value the
 * list does not know is ignored, as if the filter were not set.
 */
final readonly class SyncRunFilter
{
    /** Pseudo source of the filter: the simulations (dry-run), whatever their source. */
    public const SIMULATION = 'simulation';

    /** Periods offered, in days. */
    public const PERIODS = [1 => '24 dernières heures', 7 => '7 derniers jours', 30 => '30 derniers jours', 90 => '90 derniers jours'];

    public function __construct(
        public ?SyncStatus $status = null,
        public ?SyncSource $source = null,
        public bool $simulationsOnly = false,
        public ?int $days = null,
    ) {
    }

    public static function fromQuery(string $status, string $source, string $period): self
    {
        $days = ctype_digit($period) ? (int) $period : null;

        return new self(
            SyncStatus::tryFrom($status),
            SyncSource::tryFrom($source),
            self::SIMULATION === $source,
            null !== $days && isset(self::PERIODS[$days]) ? $days : null,
        );
    }

    /** Start of the period, null for every run. */
    public function since(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        return null === $this->days ? null : $now->modify(sprintf('-%d days', $this->days));
    }

    public function isEmpty(): bool
    {
        return null === $this->status && null === $this->source && !$this->simulationsOnly && null === $this->days;
    }

    /** @return array<string, string> query of the list (statut, source, periode) */
    public function toQuery(): array
    {
        return array_filter([
            'statut' => $this->status->value ?? '',
            'source' => $this->simulationsOnly ? self::SIMULATION : ($this->source->value ?? ''),
            'periode' => null === $this->days ? '' : (string) $this->days,
        ], static fn (string $value): bool => '' !== $value);
    }
}
