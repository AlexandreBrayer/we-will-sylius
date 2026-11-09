<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

use App\Entity\Content\CmsSection;

/**
 * Reusable sections of the builder (D12), shared by the administrators. add(), save() and remove() write
 * at once. Removing a section changes no page: an inserted section is a copy of its blocks.
 */
interface CmsSections
{
    /**
     * @return list<CmsSection> by name (case ignored), then oldest first
     */
    public function all(): array;

    public function find(int $id): ?CmsSection;

    public function add(CmsSection $section): void;

    public function save(CmsSection $section): void;

    public function remove(CmsSection $section): void;
}
