<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * "Commercialisé sur le site" in the Yack catalogue. A product that is not sold online stays visible
 * (reference, description, documentation) for every profile, without price and without cart.
 */
final class OnlineSale
{
    /** Product attribute holding the flag (checkbox), filled by the catalogue sync. */
    public const ATTRIBUTE = 'vendu_en_ligne';

    /** Reason given when a cart line is refused. */
    public const NOT_SOLD_ONLINE = 'Ce produit n\'est pas commercialisé en ligne.';

    /** Value of the attribute; a product without the attribute (spare parts) is sold online. */
    public static function isSoldOnline(mixed $attributeValue): bool
    {
        return null === $attributeValue || in_array($attributeValue, [true, 1, '1'], true);
    }
}
