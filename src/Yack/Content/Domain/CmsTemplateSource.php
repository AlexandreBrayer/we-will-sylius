<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** Where the effective template of a page comes from. */
enum CmsTemplateSource: string
{
    /** Assigned to the product itself. */
    case Product = 'product';

    /** Assigned to the category itself or to its closest ancestor holding one. */
    case Taxon = 'taxon';

    /** Default template of the type. */
    case Default = 'default';
}
