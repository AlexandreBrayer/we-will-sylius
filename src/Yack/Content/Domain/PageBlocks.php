<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Validation and parsing of CMS content (page or template): an ordered list of {type, props, style}
 * blocks restricted to BlockType. Anything else (unknown block, unknown prop, value outside the
 * allowed choices) is refused, so the content always renders with the Yack components.
 *
 * Style (optional, {} when absent): validated by BlockStyle against the builder level of the
 * author ($level). The default level, `libre`, is the one of fixtures and commands; a caller
 * that knows the author (administrator writing through the API) passes its level.
 *
 * Images (BlockType::imageProps()): a historical visual (BlockType::IMAGES) or an image of the media
 * library ($media). The library is a port given by the caller, so this class stays free of Doctrine:
 * the validation of the entities passes it (Infrastructure\Validator\ValidCmsBlocksValidator). Without
 * it (entity setters, fixtures), an uploaded path is checked on its format only
 * (CmsMediaFile::PATH_PATTERN) and its existence is left to the validation.
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
     * @return list<array{type: string, props: array<string, string>, style: array<string, string>}>
     *
     * @throws InvalidPageBlocks malformed content, whatever the level, or an image that is neither a historical visual nor in $media
     * @throws StyleNotAllowed   a style value outside the charte and $level does not allow it
     */
    public static function normalize(mixed $blocks, ?CmsTemplateType $template = null, BuilderLevel $level = BuilderLevel::Libre, ?CmsMediaLibrary $media = null): array
    {
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new InvalidPageBlocks('Le contenu doit être une liste de blocs.');
        }
        if (count($blocks) > self::MAX_BLOCKS) {
            throw new InvalidPageBlocks(sprintf('Une page contient au plus %d blocs.', self::MAX_BLOCKS));
        }

        $normalized = [];
        foreach ($blocks as $position => $block) {
            $normalized[] = self::normalizeBlock($block, $position + 1, $template, $level, $media);
        }

        if (null !== $template) {
            self::assertMandatoryBlock($normalized, $template);
        }

        return $normalized;
    }

    /**
     * Style values outside the charte of every block (BlockStyle::deviations()), in block order.
     * Reads stored content as is: a block without style has no deviation.
     *
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $blocks
     *
     * @return list<StyleDeviation>
     */
    public static function deviations(array $blocks): array
    {
        $deviations = [];
        foreach ($blocks as $position => $block) {
            array_push($deviations, ...BlockStyle::deviations($block['style'] ?? [], $position + 1));
        }

        return $deviations;
    }

    /**
     * Paths of the images shown by the blocks (BlockType::imageProps(), empty values left out), in block
     * order, without duplicates. Reads stored content as is: an unknown block or prop is ignored.
     *
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $blocks
     *
     * @return list<string>
     */
    public static function images(array $blocks): array
    {
        $images = [];
        foreach ($blocks as $block) {
            $type = BlockType::tryFrom($block['type']);
            foreach (null === $type ? [] : $type->imageProps() as $name) {
                $value = $block['props'][$name] ?? '';
                if ('' !== $value && !in_array($value, $images, true)) {
                    $images[] = $value;
                }
            }
        }

        return $images;
    }

    /**
     * @param list<array{type: string, props: array<string, string>, style: array<string, string>}> $blocks
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
     * @return array{type: string, props: array<string, string>, style: array<string, string>}
     */
    private static function normalizeBlock(mixed $block, int $position, ?CmsTemplateType $template, BuilderLevel $level, ?CmsMediaLibrary $media): array
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
        $images = $type->imageProps();
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
            $image = in_array($name, $images, true);
            if (null !== $choices && !in_array($value, $choices, true) && !($image && self::isLibraryImage($value, $media))) {
                throw new InvalidPageBlocks(sprintf(
                    'Bloc n°%d (%s) : « %s » n\'est pas une valeur autorisée pour « %s »%s.',
                    $position,
                    $type->label(),
                    $value,
                    $name,
                    $image && '' !== $value ? ' : ni une image de la médiathèque, ni un visuel Yack' : '',
                ));
            }
            $normalized[$name] = $value;
        }

        if (BlockType::Hero === $type && 'photo' === $normalized['theme'] && '' === $normalized['image']) {
            throw new InvalidPageBlocks(sprintf('Bloc n°%d (%s) : le fond « photo » demande un visuel.', $position, $type->label()));
        }

        return ['type' => $type->value, 'props' => $normalized, 'style' => BlockStyle::normalize($block['style'] ?? null, $level, $position)];
    }

    private static function isLibraryImage(string $value, ?CmsMediaLibrary $media): bool
    {
        return CmsMediaFile::isUploadedPath($value) && (null === $media || $media->exists($value));
    }
}
