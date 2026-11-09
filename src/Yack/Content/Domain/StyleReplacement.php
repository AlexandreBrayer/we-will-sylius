<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Bulk change of the style of the blocks: on one property, a free value (a deviation, StyleDeviation)
 * becomes a value of the charte (Charte), wherever a block holds it. Only the properties that accept a
 * free value (colours, title size, spacings) can be replaced; the new value is a key of the charte of
 * the same kind (a colour for a colour). Pure: the caller loads the content and saves it.
 */
final readonly class StyleReplacement
{
    private const KINDS = [BlockStyle::KIND_COLOR, BlockStyle::KIND_TITLE_SIZE, BlockStyle::KIND_SPACING];

    /** Longest piece of a refused value repeated in a message. */
    private const QUOTED_MAX = 40;

    private function __construct(
        public string $property,
        public string $from,
        public string $to,
    ) {
    }

    /**
     * @throws InvalidStyleReplacement unknown property or one without free value, $from not a free value of it, $to not a charte key of its kind
     */
    public static function of(string $property, string $from, string $to): self
    {
        $from = self::freeValue($property, $from);
        $keys = array_column(self::charteValues($property), 'key');
        if (!in_array($to, $keys, true)) {
            throw new InvalidStyleReplacement(sprintf('« %s » n\'est pas une valeur de la charte pour « %s ».', self::quote($to), BlockStyle::label($property)));
        }

        return new self($property, $from, $to);
    }

    /**
     * Properties whose value can be outside the charte, in the order of BlockStyle::PROPERTIES.
     *
     * @return list<string>
     */
    public static function properties(): array
    {
        return array_values(array_filter(
            array_keys(BlockStyle::PROPERTIES),
            static fn (string $property): bool => in_array(BlockStyle::kind($property), self::KINDS, true),
        ));
    }

    /**
     * Values of the charte a property can take (empty for a property without free value).
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public static function charteValues(string $property): array
    {
        $family = match (BlockStyle::kind($property)) {
            BlockStyle::KIND_COLOR => Charte::COLORS,
            BlockStyle::KIND_TITLE_SIZE => Charte::TITLE_SIZES,
            BlockStyle::KIND_SPACING => Charte::SPACINGS,
            default => [],
        };

        $values = [];
        foreach ($family as $key => [$label, $value]) {
            $values[] = ['key' => (string) $key, 'label' => $label, 'value' => $value];
        }

        return $values;
    }

    /**
     * The free value as blocks store it (#rrggbb in lower case, Npx).
     *
     * @throws InvalidStyleReplacement not a well formed free value of the property, or a value of the charte
     */
    public static function freeValue(string $property, string $value): string
    {
        if (!in_array($property, self::properties(), true)) {
            throw new InvalidStyleReplacement(sprintf('Le réglage « %s » n\'a pas de valeur hors charte.', self::quote(BlockStyle::label($property))));
        }
        if ('' === $value) {
            throw new InvalidStyleReplacement(sprintf('Choisissez la valeur hors charte à remplacer pour « %s ».', BlockStyle::label($property)));
        }

        try {
            $style = BlockStyle::normalize([$property => $value]);
        } catch (InvalidPageBlocks) {
            throw new InvalidStyleReplacement(sprintf('« %s » n\'est pas une valeur valide pour « %s ».', self::quote($value), BlockStyle::label($property)));
        }

        return BlockStyle::deviations($style)[0]->value
            ?? throw new InvalidStyleReplacement(sprintf('« %s » est déjà une valeur de la charte pour « %s ».', self::quote($value), BlockStyle::label($property)));
    }

    /** Label of the new value: « Magenta (#a33c87) », « Grand (40px) ». */
    public function toLabel(): string
    {
        foreach (self::charteValues($this->property) as $value) {
            if ($value['key'] === $this->to) {
                return sprintf('%s (%s)', $value['label'], $value['value']);
            }
        }

        return $this->to;
    }

    /** CSS value of the new value in the charte (#a33c87, 40px). */
    public function toValue(): string
    {
        foreach (self::charteValues($this->property) as $value) {
            if ($value['key'] === $this->to) {
                return $value['value'];
            }
        }

        return $this->to;
    }

    /**
     * Positions (from 1) of the blocks holding the free value on the property; stored content is read as
     * is and never fails on it (style absent tolerated, case of the value ignored). As in the report
     * (PageBlocks::deviations()), a block that is not a map with a string `type`, or a value that is not a
     * string, holds nothing.
     *
     * @param array<mixed> $blocks
     *
     * @return list<int>
     */
    public function matches(array $blocks): array
    {
        $positions = [];
        foreach (array_values($blocks) as $index => $block) {
            $style = is_array($block) && is_string($block['type'] ?? null) && is_array($block['style'] ?? null) ? $block['style'] : [];
            $value = $style[$this->property] ?? '';
            if (is_string($value) && strtolower($value) === $this->from) {
                $positions[] = $index + 1;
            }
        }

        return $positions;
    }

    /**
     * The blocks with the free value replaced by the charte key; the rest of the content (keys, blocks
     * that hold nothing, malformed ones included) is unchanged.
     *
     * @param array<mixed> $blocks
     *
     * @return array<mixed>
     */
    public function apply(array $blocks): array
    {
        $positions = $this->matches($blocks);
        $position = 0;
        foreach ($blocks as $key => $block) {
            ++$position;
            if (is_array($block) && in_array($position, $positions, true)) {
                $style = is_array($block['style'] ?? null) ? $block['style'] : [];
                $block['style'] = [...$style, $this->property => $this->to];
                $blocks[$key] = $block;
            }
        }

        return $blocks;
    }

    private static function quote(string $value): string
    {
        return mb_strlen($value) > self::QUOTED_MAX ? mb_substr($value, 0, self::QUOTED_MAX) . '...' : $value;
    }
}
