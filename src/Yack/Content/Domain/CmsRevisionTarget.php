<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** Kind of content a revision (App\Entity\Content\CmsRevision) keeps a version of. */
enum CmsRevisionTarget: string
{
    case Page = 'page';
    case Template = 'template';
}
