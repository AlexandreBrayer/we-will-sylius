<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Validation and parsing of CMS content (page or template): an ordered list of {type, props}
 * blocks restricted to BlockType. Anything else (unknown block, unknown prop, value outside the
 * allowed choices) is refused, so the content always renders with the Yack components.
 *
 * Templates ($template given): blocks bound to the other template type are refused, and the
 * type's mandatory block (CmsTemplateType::mandatoryBlock()) must be present exactly once.
 * Pages ($template null): bound blocks are refused.
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
    public static function normalize(mixed $blocks, ?CmsTemplateType $template = null): array
    {
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new InvalidPageBlocks('Le contenu doit être une liste de blocs.');
        }
        if (count($blocks) > self::MAX_BLOCKS) {
            throw new InvalidPageBlocks(sprintf('Une page contient au plus %d blocs.', self::MAX_BLOCKS));
        }

        $normalized = [];
        foreach ($blocks as $position => $block) {
            $normalized[] = self::normalizeBlock($block, $position + 1, $template);
        }

        if (null !== $template) {
            self::assertMandatoryBlock($normalized, $template);
        }

        return $normalized;
    }

    /**
     * @param list<array{type: string, props: array<string, string>}> $blocks
     *
     * @throws InvalidPageBlocks
     */
    private static function assertMandatoryBlock(array $blocks, CmsTemplateType $template): void
    {
        $mandatory = $template->mandatoryBlock();
        $count = count(array_filter($blocks, static fn (array $block): bool => $block['type'] === $mandatory->value));

        if (0 === $count) {
            throw new InvalidPageBlocks(sprintf('Un template %s doit contenir le bloc « %s ».', mb_strtolower($template->label()), $mandatory->label()));
        }
        if ($count > 1) {
            throw new InvalidPageBlocks(sprintf('Le bloc « %s » ne peut figurer qu\'une seule fois dans le template.', $mandatory->label()));
        }
    }

    /**
     * @return array{type: string, props: array<string, string>}
     */
    private static function normalizeBlock(mixed $block, int $position, ?CmsTemplateType $template): array
    {
        if (!is_array($block) || !is_string($block['type'] ?? null)) {
            throw new InvalidPageBlocks(sprintf('Bloc n°%d : type manquant.', $position));
        }
        $type = BlockType::tryFrom($block['type']) ?? throw new InvalidPageBlocks(sprintf('Bloc n°%d : type « %s » inconnu.', $position, $block['type']));

        $scope = $type->scope();
        if (null !== $scope && $scope !== $template) {
            throw new InvalidPageBlocks(sprintf('Bloc n°%d (%s) : réservé aux templates %s.', $position, $type->label(), mb_strtolower($scope->label())));
        }

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

        if (BlockType::Hero === $type && 'photo' === $normalized['theme'] && '' === $normalized['image']) {
            throw new InvalidPageBlocks(sprintf('Bloc n°%d (%s) : le fond « photo » demande un visuel.', $position, $type->label()));
        }

        return ['type' => $type->value, 'props' => $normalized];
    }
}
