<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * A free value (outside the charte) in the style of a block: block position (from 1), style
 * property and value. Computed on reading (BlockStyle::deviations()), never stored.
 */
final readonly class StyleDeviation
{
    public function __construct(
        public int $block,
        public string $property,
        public string $value,
    ) {
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
