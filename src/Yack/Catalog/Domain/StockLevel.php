<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Stock of a sellable variant as shown to customers (catalogue, product page, SAV parts list).
 * Yack (ERP) is the master of the stock, synchronised into the Sylius "on hand" quantity.
 *
 * The level carries the display rule ("Plus que N" under the low stock threshold), so that no
 * front-end ever hard-codes a threshold.
 */
final readonly class StockLevel
{
    public const IN_STOCK = 'en_stock';
    public const LOW = 'faible';
    public const ON_ORDER = 'sur_commande';
    public const UNAVAILABLE = 'indisponible';

    /**
     * Below this available quantity (excluded), the level is "faible" ("Plus que N").
     * HYPOTHESIS: value of the commercial demo, to be confirmed by Yack.
     */
    public const LOW_STOCK_THRESHOLD = 10;

    private function __construct(
        public string $level,
        public bool $tracked,
        public ?int $available,
        public bool $inStock,
        private bool $noLongerSold = false,
    ) {
    }

    /**
     * @param int|null $available on hand minus on hold, null when the stock is not tracked
     * @param bool     $inStock   the variant can be sold now (Sylius availability checker)
     */
    public static function of(bool $tracked, ?int $available, bool $inStock): self
    {
        $available = $tracked ? max(0, (int) $available) : null;

        $level = match (true) {
            !$inStock => self::ON_ORDER,
            null !== $available && $available > 0 && $available < self::LOW_STOCK_THRESHOLD => self::LOW,
            default => self::IN_STOCK,
        };

        return new self($level, $tracked, $available, $inStock);
    }

    /** No sellable variant: the product cannot be ordered. */
    public static function noVariant(): self
    {
        return new self(self::UNAVAILABLE, false, null, false);
    }

    /** Spare part no longer sold (disabled product), still listed in the SAV exploded views. */
    public function withdrawn(): self
    {
        return new self(self::UNAVAILABLE, $this->tracked, $this->available, $this->inStock, true);
    }

    /** Label of the level, as shown on the product cards ("Plus que 3"). */
    public function label(): string
    {
        if ($this->noLongerSold) {
            return 'Plus commercialisée';
        }

        return match ($this->level) {
            self::LOW => sprintf('Plus que %d', (int) $this->available),
            self::IN_STOCK => 'En stock',
            self::ON_ORDER => 'Sur commande',
            default => 'Indisponible',
        };
    }

    /**
     * Availability label of the API contract (`stock.label`, docs/API_FRONT.md §4 and §7):
     * "En stock" also for a low stock, the SAV parts list shows the quantity next to it.
     */
    public function availabilityLabel(): string
    {
        return self::LOW === $this->level ? 'En stock' : $this->label();
    }

    /**
     * Shop API shape: the keys of docs/API_FRONT.md §4 plus `level`.
     *
     * @return array{tracked: bool, available: int|null, inStock: bool, label: string, level: string}
     */
    public function toArray(): array
    {
        return [
            'tracked' => $this->tracked,
            'available' => $this->available,
            'inStock' => $this->inStock,
            'label' => $this->availabilityLabel(),
            'level' => $this->level,
        ];
    }
}
