<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\Content\CmsPage;

interface CmsPages
{
    public function findPublished(string $slug): ?CmsPage;

    /** @return list<CmsPage> published pages, by title */
    public function published(): array;

    /** @return list<CmsPage> every page, last modified first */
    public function all(): array;
}
