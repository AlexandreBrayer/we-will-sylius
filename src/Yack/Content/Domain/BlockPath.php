<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Where a block sits in a CMS content, as editors read it in a message: « Bloc n°2 » for a block of the
 * page, « Section 2 » / « Colonnes 2 » for a container of the page, then one segment per level inside the
 * containers: « Section 2 › colonnes 1 › colonne 2 › bloc 3 ». `root` is the position (from 1) of the
 * block of the page that holds it (itself at the root), what the builder and the reports point to.
 */
final readonly class BlockPath
{
    private function __construct(
        public int $root,
        private string $label,
    ) {
    }

    /** Block at $position (from 1) of the page; $type null for a node whose type is unknown. */
    public static function root(int $position, ?BlockType $type = null): self
    {
        return new self($position, match ($type) {
            BlockType::Section => sprintf('Section %d', $position),
            BlockType::Columns => sprintf('Colonnes %d', $position),
            default => sprintf('Bloc n°%d', $position),
        });
    }

    /** Block at $position (from 1) inside this container (section) or this column. */
    public function child(int $position, ?BlockType $type = null): self
    {
        return new self($this->root, sprintf('%s › %s %d', $this->label, match ($type) {
            BlockType::Section => 'section',
            BlockType::Columns => 'colonnes',
            default => 'bloc',
        }, $position));
    }

    /** Column $column (from 1) of this columns block. */
    public function column(int $column): self
    {
        return new self($this->root, sprintf('%s › colonne %d', $this->label, $column));
    }

    public function label(): string
    {
        return $this->label;
    }

    /** The path followed by the label of a content block (« Section 2 › bloc 3 (FAQ) »); a container is already named by its path. */
    public function named(BlockType $type): string
    {
        return $type->isContainer() ? $this->label : sprintf('%s (%s)', $this->label, $type->label());
    }
}
