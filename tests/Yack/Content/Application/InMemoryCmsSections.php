<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsSection;
use App\Yack\Content\Domain\CmsSections;

/** Hand-written fake of the sections port: ids given on add(), by name (case ignored), counts the writes. */
final class InMemoryCmsSections implements CmsSections
{
    /** @var array<int, CmsSection> */
    public array $sections = [];

    public int $saves = 0;

    private int $nextId = 1;

    public function all(): array
    {
        $all = array_values($this->sections);
        usort($all, static fn (CmsSection $a, CmsSection $b): int => [mb_strtolower($a->getName()), $a->getId()] <=> [mb_strtolower($b->getName()), $b->getId()]);

        return $all;
    }

    public function find(int $id): ?CmsSection
    {
        return $this->sections[$id] ?? null;
    }

    public function add(CmsSection $section): void
    {
        $id = $this->nextId++;
        (new \ReflectionProperty(CmsSection::class, 'id'))->setValue($section, $id);
        $this->sections[$id] = $section;
    }

    public function save(CmsSection $section): void
    {
        ++$this->saves;
    }

    public function remove(CmsSection $section): void
    {
        unset($this->sections[(int) $section->getId()]);
    }
}
