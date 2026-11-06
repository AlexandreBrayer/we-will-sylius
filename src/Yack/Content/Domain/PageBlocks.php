<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Validation and parsing of a CMS page content: an ordered list of {type, props} blocks
 * restricted to BlockType. Anything else (unknown block, unknown prop, value outside the
 * allowed choices) is refused, so the page always renders with the Yack components.
 */
final class PageBlocks
{
    public const MAX_BLOCKS = 30;

    public const MAX_PROP_LENGTH = 4000;

    /**
     * @return list<array{type: string, props: array<string, string>}>
     *
     * @throws InvalidPageBlocks
     */
    public static function normalize(mixed $blocks): array
    {
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new InvalidPageBlocks('Le contenu doit être une liste de blocs.');
        }
        if (count($blocks) > self::MAX_BLOCKS) {
            throw new InvalidPageBlocks(sprintf('Une page contient au plus %d blocs.', self::MAX_BLOCKS));
        }

        $normalized = [];
        foreach ($blocks as $position => $block) {
            $normalized[] = self::normalizeBlock($block, $position + 1);
        }

        return $normalized;
    }

    /**
     * @return array{type: string, props: array<string, string>}
     */
    private static function normalizeBlock(mixed $block, int $position): array
    {
        if (!is_array($block) || !is_string($block['type'] ?? null)) {
            throw new InvalidPageBlocks(sprintf('Bloc n°%d : type manquant.', $position));
        }
        $type = BlockType::tryFrom($block['type']) ?? throw new InvalidPageBlocks(sprintf('Bloc n°%d : type « %s » inconnu.', $position, $block['type']));

        $props = $block['props'] ?? [];
        if (!is_array($props)) {
            throw new InvalidPageBlocks(sprintf('Bloc n°%d (%s) : propriétés invalides.', $position, $type->label()));
        }

        $allowed = $type->props();
        $unknown = array_diff(array_map('strval', array_keys($props)), array_keys($allowed));
        if ([] !== $unknown) {
            throw new InvalidPageBlocks(sprintf('Bloc n°%d (%s) : propriété(s) non autorisée(s) : %s.', $position, $type->label(), implode(', ', $unknown)));
        }

        $normalized = [];
        foreach ($allowed as $name => $choices) {
            $value = $props[$name] ?? ($choices[0] ?? '');
            if (!is_string($value) || mb_strlen($value) > self::MAX_PROP_LENGTH) {
                throw new InvalidPageBlocks(sprintf('Bloc n°%d (%s) : valeur invalide pour « %s ».', $position, $type->label(), $name));
            }
            if (null !== $choices && !in_array($value, $choices, true)) {
                throw new InvalidPageBlocks(sprintf('Bloc n°%d (%s) : « %s » n\'est pas une valeur autorisée pour « %s ».', $position, $type->label(), $value, $name));
            }
            $normalized[$name] = $value;
        }

        return ['type' => $type->value, 'props' => $normalized];
    }
}
