<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\Content\CmsMedia;

/**
 * Images of the builder media library. An image prop of a block accepts a path of the library or a
 * historical visual (PageBlocks::normalize()). add(), save() and remove() write at once.
 */
interface CmsMediaLibrary
{
    /** True when an image of the library has exactly this path. */
    public function exists(string $path): bool;

    /**
     * @return list<CmsMedia> newest first; when $query is given, only the images whose name contains it (case ignored)
     */
    public function search(?string $query = null): array;

    public function find(int $id): ?CmsMedia;

    public function add(CmsMedia $media): void;

    public function save(CmsMedia $media): void;

    public function remove(CmsMedia $media): void;
}
