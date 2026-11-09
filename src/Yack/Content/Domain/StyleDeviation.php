<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * A free value (outside the charte) in the style of a block: position (from 1) of the block of the page
 * that holds it, style property and value, and where it is (BlockPath::label(): « Bloc n°2 », « Section 1 ›
 * colonnes 1 › colonne 2 › bloc 1 ») with the type of the block holding the style ('' when unknown).
 * Computed on reading (BlockStyle::deviations()), never stored.
 */
final readonly class StyleDeviation
{
    public string $where;

    public function __construct(
        public int $block,
        public string $property,
        public string $value,
        string $where = '',
        public string $type = '',
    ) {
        $this->where = '' === $where ? BlockPath::root($block)->label() : $where;
    }

    /** French label of the property (« Fond », « Taille du titre »…). */
    public function propertyLabel(): string
    {
        return BlockStyle::label($this->property);
    }

    /** The value in a sentence: « la couleur #ff7700 », « la taille de titre 40px », « l'espacement 120px ». */
    public function describe(): string
    {
        return match (BlockStyle::kind($this->property)) {
            BlockStyle::KIND_COLOR => sprintf('la couleur %s', $this->value),
            BlockStyle::KIND_TITLE_SIZE => sprintf('la taille de titre %s', $this->value),
            default => sprintf('l\'espacement %s', $this->value),
        };
    }
}
