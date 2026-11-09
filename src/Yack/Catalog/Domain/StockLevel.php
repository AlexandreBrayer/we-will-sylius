<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Stock of a sellable variant as shown to customers (catalogue, product page, SAV parts list).
 * Yack (ERP) is the master of the stock, synchronised into the Sylius "on hand" quantity.
 *
 * Availability is binary: in stock (orderable) or out of stock (not orderable). A spare part no
 * longer sold keeps its own level in the SAV exploded views: it is a withdrawal, not a stock state.
 */
final readonly class StockLevel
{
    public const IN_STOCK = 'en_stock';

    public const OUT_OF_STOCK = 'rupture';

    public const WITHDRAWN = 'plus_commercialisee';

    private function __construct(
        public string $level,
        public bool $tracked,
        public ?int $available,
        public bool $inStock,
    ) {
    }

    /**
     * @param int|null $available on hand minus on hold, null when the stock is not tracked
     * @param bool     $inStock   the variant can be sold now (Sylius availability checker)
     */
    public static function of(bool $tracked, ?int $available, bool $inStock): self
    {
        return new self(
            $inStock ? self::IN_STOCK : self::OUT_OF_STOCK,
            $tracked,
            $tracked ? max(0, (int) $available) : null,
            $inStock,
        );
    }

    /** No sellable variant: the product cannot be ordered. */
    public static function noVariant(): self
    {
        return new self(self::OUT_OF_STOCK, false, null, false);
    }

    /** Spare part no longer sold (disabled product), still listed in the SAV exploded views. */
    public function withdrawn(): self
    {
        return new self(self::WITHDRAWN, $this->tracked, $this->available, $this->inStock);
    }

    /** Label of the level, as shown on the product cards, the product page and the SAV parts list. */
    public function label(): string
    {
        return match ($this->level) {
            self::IN_STOCK => 'En stock',
            self::WITHDRAWN => 'Plus commercialisée',
            default => 'Rupture de stock',
        };
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
            'label' => $this->label(),
            'level' => $this->level,
        ];
    }
}
