<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Validation and parsing of CMS content (page or template): an ordered list of {type, props, style}
 * blocks restricted to BlockType. Anything else (unknown block, unknown prop, value outside the
 * allowed choices) is refused, so the content always renders with the Yack components.
 *
 * Layout containers (BlockType::isContainer()) make the list a tree of two container levels at most:
 * a `section` holds its blocks in `children`, a `colonnes` block its columns in `columns` (one list of
 * blocks per column, as many as its ratio imposes). Where each block may go is BlockType::allowedIn();
 * a refusal names the path of the block (BlockPath: « Section 2 › colonnes 1 › colonne 1 › bloc 3 »).
 * Content without container (every page saved before them) is valid as it is.
 *
 * Style (optional, {} when absent): validated by BlockStyle against the builder level of the
 * author ($level), restricted to BlockType::styleProperties(). The default level, `libre`, is the one
 * of fixtures and commands; a caller that knows the author (administrator writing through the API)
 * passes its level.
 *
 * Images (BlockType::imageProps()): a historical visual (BlockType::IMAGES) or an image of the media
 * library ($media). The library is a port given by the caller, so this class stays free of Doctrine:
 * the validation of the entities passes it (Infrastructure\Validator\ValidCmsBlocksValidator). Without
 * it (entity setters, fixtures), an uploaded path is checked on its format only
 * (CmsMediaFile::PATH_PATTERN) and its existence is left to the validation.
 *
 * Short texts (BlockType::shortTextProps(): titles, labels) hold on one line, BlockType::SHORT_TEXT_MAX
 * characters at most; a required prop (BlockType::requiredProps(): the title of a title block) is not blank.
 *
 * Links (BlockType::linkProps(), and the link of each « Libellé | lien » line of BlockType::linkListProps()):
 * empty or a target of BlockLink (page:<slug>, path of the site, http(s), mailto, tel), whatever the level;
 * anything else (javascript:, data:, //host…) is refused.
 *
 * Templates ($template given): blocks bound to the other template type are refused, bound blocks stay
 * at the root (the product zones of the front are derived from it), and the type's mandatory block
 * (CmsTemplateType::mandatoryBlock()) must be present exactly once. Pages ($template null): bound blocks
 * are refused.
 */
final class PageBlocks
{
    /** Most blocks in a content, containers and their blocks included (count()). */
    public const MAX_BLOCKS = 30;

    public const MAX_PROP_LENGTH = 4000;

    /**
     * @return list<array{type: string, props: array<string, string>, style: array<string, string>, children?: list<array<string, mixed>>, columns?: list<list<array<string, mixed>>>}>
     *
     * @throws InvalidPageBlocks malformed content, whatever the level, or an image that is neither a historical visual nor in $media
     * @throws StyleNotAllowed   a style value outside the charte and $level does not allow it
     */
    public static function normalize(mixed $blocks, ?CmsTemplateType $template = null, BuilderLevel $level = BuilderLevel::Libre, ?CmsMediaLibrary $media = null): array
    {
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new InvalidPageBlocks('Le contenu doit être une liste de blocs.');
        }
        if (count($blocks) > self::MAX_BLOCKS || self::count($blocks) > self::MAX_BLOCKS) {
            throw new InvalidPageBlocks(sprintf('Une page contient au plus %d blocs (sections, colonnes et leurs blocs compris).', self::MAX_BLOCKS));
        }

        $normalized = [];
        foreach ($blocks as $index => $block) {
            $normalized[] = self::normalizeBlock($block, null, $index + 1, null, $template, $level, $media);
        }

        if (null !== $template) {
            self::assertMandatoryBlock($normalized, $template);
        }

        return $normalized;
    }

    /**
     * Number of blocks of the content, containers and the blocks they hold included (walk()).
     *
     * @param array<mixed> $blocks
     */
    public static function count(array $blocks): int
    {
        return count(self::walk($blocks));
    }

    /**
     * Every block of the content with where it is and its type as stored, in document order: a container,
     * then its blocks (a section's children, then the columns in order). Reads stored content as is and
     * never fails on it: an entry that is not a map with a string `type` is skipped (its position still
     * counts), a `children` / `columns` that is not a list of lists holds nothing.
     *
     * @param array<mixed> $blocks
     *
     * @return list<array{BlockPath, array<mixed>, string}>
     */
    public static function walk(array $blocks): array
    {
        $nodes = [];
        self::map($blocks, static function (array $block, BlockPath $path, string $type) use (&$nodes): array {
            $nodes[] = [$path, $block, $type];

            return $block;
        });

        return $nodes;
    }

    /**
     * The content with $change applied to every block (walk() order), its keys and the entries that are not
     * blocks left as they are. $change receives the block, its path and its type as stored, and returns the
     * block; the blocks of a container are changed after it, inside what it returned.
     *
     * @param array<mixed>                                            $blocks
     * @param callable(array<mixed>, BlockPath, string): array<mixed> $change
     *
     * @return array<mixed>
     */
    public static function map(array $blocks, callable $change): array
    {
        return self::mapIn($blocks, null, $change);
    }

    /**
     * Style values outside the charte of every block (BlockStyle::deviations()), containers and nested
     * blocks included, in walk() order. Reads stored content as is and never fails on it: a block without
     * style has no deviation, a block that is not a map with a string `type`, or a malformed style value,
     * is ignored.
     *
     * @param array<mixed> $blocks
     *
     * @return list<StyleDeviation>
     */
    public static function deviations(array $blocks): array
    {
        $deviations = [];
        foreach (self::walk($blocks) as [$path, $block, $type]) {
            array_push($deviations, ...BlockStyle::deviations($block['style'] ?? [], $path, $type));
        }

        return $deviations;
    }

    /**
     * Paths of the images shown by the blocks (BlockType::imageProps(), empty values left out), nested blocks
     * included, in walk() order, without duplicates. Reads stored content as is: an unknown block or prop, or
     * a value that is not a string, is ignored.
     *
     * @param array<mixed> $blocks
     *
     * @return list<string>
     */
    public static function images(array $blocks): array
    {
        $images = [];
        foreach (self::walk($blocks) as [, $block, $stored]) {
            $type = BlockType::tryFrom($stored);
            $props = null === $type || !is_array($block['props'] ?? null) ? [] : $block['props'];
            foreach (null === $type ? [] : $type->imageProps() as $name) {
                $value = $props[$name] ?? '';
                if (is_string($value) && '' !== $value && !in_array($value, $images, true)) {
                    $images[] = $value;
                }
            }
        }

        return $images;
    }

    /**
     * @param array<mixed>                                            $blocks
     * @param callable(array<mixed>, BlockPath, string): array<mixed> $change
     *
     * @return array<mixed>
     */
    private static function mapIn(array $blocks, ?BlockPath $parent, callable $change): array
    {
        $position = 0;
        foreach ($blocks as $key => $block) {
            ++$position;
            if (!is_array($block) || !is_string($block['type'] ?? null)) {
                continue;
            }
            $type = BlockType::tryFrom($block['type']);
            $path = null === $parent ? BlockPath::root($position, $type) : $parent->child($position, $type);
            $block = $change($block, $path, $block['type']);
            if (is_array($block['children'] ?? null)) {
                $block['children'] = self::mapIn($block['children'], $path, $change);
            }
            if (is_array($block['columns'] ?? null)) {
                $column = 0;
                foreach ($block['columns'] as $index => $blocksOfColumn) {
                    ++$column;
                    if (is_array($blocksOfColumn)) {
                        $block['columns'][$index] = self::mapIn($blocksOfColumn, $path->column($column), $change);
                    }
                }
            }
            $blocks[$key] = $block;
        }

        return $blocks;
    }

    /**
     * @param list<array{type: string, props: array<string, string>, style: array<string, string>, children?: list<array<string, mixed>>, columns?: list<list<array<string, mixed>>>}> $blocks
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
     * Block at $position (from 1) of the page ($parentPath null) or of a section / column ($parentPath, $parent:
     * the section, or Columns for a column).
     *
     * @return array{type: string, props: array<string, string>, style: array<string, string>, children?: list<array<string, mixed>>, columns?: list<list<array<string, mixed>>>}
     */
    private static function normalizeBlock(mixed $block, ?BlockPath $parentPath, int $position, ?BlockType $parent, ?CmsTemplateType $template, BuilderLevel $level, ?CmsMediaLibrary $media): array
    {
        if (!is_array($block) || !is_string($block['type'] ?? null)) {
            throw new InvalidPageBlocks(sprintf('%s : type manquant.', self::pathOf($parentPath, $position, null)->label()));
        }
        $type = BlockType::tryFrom($block['type'])
            ?? throw new InvalidPageBlocks(sprintf('%s : type « %s » inconnu.', self::pathOf($parentPath, $position, null)->label(), $block['type']));
        $path = self::pathOf($parentPath, $position, $type);
        $where = $path->label();
        $named = $path->named($type);

        $scope = $type->scope();
        if (null !== $scope && $scope !== $template) {
            throw new InvalidPageBlocks(sprintf('%s : réservé aux templates %s.', $named, mb_strtolower($scope->label())));
        }
        if (!$type->allowedIn($parent)) {
            throw new InvalidPageBlocks(sprintf('%s : %s', $named, self::misplaced($type)));
        }
        self::assertContentKeys($block, $type, $named);

        $props = $block['props'] ?? [];
        if (!is_array($props)) {
            throw new InvalidPageBlocks(sprintf('%s : propriétés invalides.', $named));
        }

        $allowed = $type->props();
        $images = $type->imageProps();
        $links = $type->linkProps();
        $unknown = array_diff(array_map('strval', array_keys($props)), array_keys($allowed));
        if ([] !== $unknown) {
            throw new InvalidPageBlocks(sprintf('%s : propriété(s) non autorisée(s) : %s.', $named, implode(', ', $unknown)));
        }

        $normalized = [];
        foreach ($allowed as $name => $choices) {
            $value = $props[$name] ?? ($choices[0] ?? '');
            if (!is_string($value) || mb_strlen($value) > self::MAX_PROP_LENGTH) {
                throw new InvalidPageBlocks(sprintf('%s : valeur invalide pour « %s ».', $named, $name));
            }
            $image = in_array($name, $images, true);
            if (null !== $choices && !in_array($value, $choices, true) && !($image && self::isLibraryImage($value, $media))) {
                throw new InvalidPageBlocks(sprintf(
                    '%s : « %s » n\'est pas une valeur autorisée pour « %s »%s.',
                    $named,
                    $value,
                    $name,
                    $image && '' !== $value ? ' : ni une image de la médiathèque, ni un visuel Yack' : '',
                ));
            }
            if (in_array($name, $links, true) && !BlockLink::isAllowed($value)) {
                throw new InvalidPageBlocks(sprintf('%s : le lien « %s » n\'est pas une adresse autorisée.', $where, BlockLink::quote($value)));
            }
            foreach (in_array($name, $type->linkListProps(), true) ? BlockLink::inLines($value) : [] as $link) {
                if (!BlockLink::isAllowed($link)) {
                    throw new InvalidPageBlocks(sprintf('%s : le lien « %s » n\'est pas une adresse autorisée.', $where, BlockLink::quote($link)));
                }
            }
            if (in_array($name, $type->imageAltProps(), true) && !BlockType::isImageAlt($value)) {
                throw new InvalidPageBlocks(sprintf(
                    '%s : le texte alternatif « %s » fait au plus %d caractères, sur une ligne, sans < ni >.',
                    $named,
                    $name,
                    CmsMediaFile::ALT_MAX,
                ));
            }
            if (in_array($name, $type->shortTextProps(), true) && (mb_strlen($value) > BlockType::SHORT_TEXT_MAX || 1 === preg_match('/[\r\n]/', $value))) {
                throw new InvalidPageBlocks(sprintf('%s : « %s » tient sur une ligne, en %d caractères au plus.', $named, $name, BlockType::SHORT_TEXT_MAX));
            }
            if (in_array($name, $type->requiredProps(), true) && '' === trim($value)) {
                throw new InvalidPageBlocks(sprintf('%s : « %s » est obligatoire.', $named, $name));
            }
            $normalized[$name] = $value;
        }

        if (BlockType::Hero === $type && 'photo' === $normalized['theme'] && '' === $normalized['image']) {
            throw new InvalidPageBlocks(sprintf('%s : le fond « photo » demande un visuel.', $named));
        }

        $result = ['type' => $type->value, 'props' => $normalized, 'style' => BlockStyle::normalize($block['style'] ?? null, $level, $path, $type)];

        return match ($type) {
            BlockType::Section => $result + ['children' => self::normalizeList($block['children'] ?? [], $path, BlockType::Section, $template, $level, $media)],
            BlockType::Columns => $result + ['columns' => self::normalizeColumns($block['columns'] ?? null, $normalized['ratio'], $path, $template, $level, $media)],
            default => $result,
        };
    }

    /**
     * Blocks of a section (or of a column: $parent Columns, $path the column).
     *
     * @return list<array<string, mixed>>
     */
    private static function normalizeList(mixed $blocks, BlockPath $path, BlockType $parent, ?CmsTemplateType $template, BuilderLevel $level, ?CmsMediaLibrary $media): array
    {
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new InvalidPageBlocks(sprintf('%s : le contenu doit être une liste de blocs.', $path->label()));
        }

        $normalized = [];
        foreach ($blocks as $index => $block) {
            $normalized[] = self::normalizeBlock($block, $path, $index + 1, $parent, $template, $level, $media);
        }

        return $normalized;
    }

    /**
     * Columns of a columns block: as many lists of blocks as the ratio has parts; absent = empty columns.
     *
     * @return list<list<array<string, mixed>>>
     */
    private static function normalizeColumns(mixed $columns, string $ratio, BlockPath $path, ?CmsTemplateType $template, BuilderLevel $level, ?CmsMediaLibrary $media): array
    {
        $expected = BlockType::columnCount($ratio);
        $columns ??= array_fill(0, $expected, []);
        if (!is_array($columns) || !array_is_list($columns)) {
            throw new InvalidPageBlocks(sprintf('%s : les colonnes doivent être une liste de colonnes.', $path->label()));
        }
        if (count($columns) !== $expected) {
            throw new InvalidPageBlocks(sprintf(
                '%s : la répartition « %s » demande %d colonnes, %d %s.',
                $path->label(),
                $ratio,
                $expected,
                count($columns),
                count($columns) > 1 ? 'reçues' : 'reçue',
            ));
        }

        $normalized = [];
        foreach ($columns as $index => $blocks) {
            $normalized[] = self::normalizeList($blocks, $path->column($index + 1), BlockType::Columns, $template, $level, $media);
        }

        return $normalized;
    }

    private static function pathOf(?BlockPath $parent, int $position, ?BlockType $type): BlockPath
    {
        return null === $parent ? BlockPath::root($position, $type) : $parent->child($position, $type);
    }

    /** Why $type cannot be where it is (BlockType::allowedIn() refused it). */
    private static function misplaced(BlockType $type): string
    {
        return match (true) {
            null !== $type->scope() => 'un bloc lié reste à la racine du template, hors des sections et des colonnes.',
            BlockType::Section === $type => 'une section se place à la racine de la page, jamais dans une section ou une colonne.',
            $type->isContainer() => 'une colonne ne contient que des blocs de contenu, ni section ni colonnes.',
            default => 'bloc pleine largeur, interdit dans une colonne.',
        };
    }

    /**
     * Only a section has `children`, only a columns block has `columns`.
     *
     * @param array<mixed> $block
     *
     * @throws InvalidPageBlocks
     */
    private static function assertContentKeys(array $block, BlockType $type, string $named): void
    {
        $misplaced = array_values(array_filter(
            ['children' => BlockType::Section, 'columns' => BlockType::Columns],
            static fn (BlockType $owner, string $key): bool => array_key_exists($key, $block) && $owner !== $type,
            \ARRAY_FILTER_USE_BOTH,
        ));
        if ([] !== $misplaced) {
            throw new InvalidPageBlocks(sprintf(
                '%s : %s',
                $named,
                $type->isContainer() ? 'une section range ses blocs dans « children », des colonnes dans « columns ».' : 'un bloc de contenu ne contient pas d\'autres blocs.',
            ));
        }
    }

    private static function isLibraryImage(string $value, ?CmsMediaLibrary $media): bool
    {
        return CmsMediaFile::isUploadedPath($value) && (null === $media || $media->exists($value));
    }
}
