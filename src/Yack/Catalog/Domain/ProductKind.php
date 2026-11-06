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

    /** Root category of the shop menu holding this kind of product (Clik&Yack / Clik&Yack Parts). */
    public function catalogTaxonCode(): string
    {
        return match ($this) {
            self::Finished => 'clik_yack',
            self::SparePart => 'clik_yack_parts',
        };
    }
}
