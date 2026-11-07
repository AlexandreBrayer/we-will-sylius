<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

enum ProductKind: string
{
    case Finished = 'finished';
    case SparePart = 'spare_part';

    /** Value of the "type_article" product attribute shown in the shop. */
    public function label(): string
    {
        return match ($this) {
            self::Finished => 'Produit fini',
            self::SparePart => 'Pièce détachée',
        };
    }

    /** Value of the `yack_type` filter and of `yack.type` in the shop API. */
    public function apiType(): string
    {
        return match ($this) {
            self::Finished => 'product',
            self::SparePart => 'part',
        };
    }

    /** Kind of a `yack_type` value of the shop API, null when the value is unknown. */
    public static function fromApiType(string $apiType): ?self
    {
        foreach (self::cases() as $kind) {
            if ($kind->apiType() === $apiType) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * Kind of a product from the codes of its main category and of its ancestors, nearest first:
     * the first code that is the root of a menu branch (Clik&Yack / Clik&Yack Parts) wins.
     *
     * @param list<string> $taxonCodes
     */
    public static function fromTaxonCodes(array $taxonCodes): ?self
    {
        foreach ($taxonCodes as $code) {
            foreach (self::cases() as $kind) {
                if ($kind->catalogTaxonCode() === $code) {
                    return $kind;
                }
            }
        }

        return null;
    }

    /** Root category of the shop menu holding this kind of product (Clik&Yack / Clik&Yack Parts). */
    public function catalogTaxonCode(): string
    {
        return match ($this) {
            self::Finished => 'clik_yack',
            self::SparePart => 'clik_yack_parts',
        };
    }
}
