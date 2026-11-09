<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Common pages a CMS template lays out: the product page (/produit/{slug}) or the
 * category page (/produits/..., /pieces/...).
 */
enum CmsTemplateType: string
{
    case Product = 'product';
    case Taxon = 'taxon';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Produit',
            self::Taxon => 'Catégorie',
        };
    }

    /** Block a template of this type must contain exactly once (never removable in the builder). */
    public function mandatoryBlock(): BlockType
    {
        return match ($this) {
            self::Product => BlockType::ProductBuyBox,
            self::Taxon => BlockType::TaxonProductGrid,
        };
    }
}
