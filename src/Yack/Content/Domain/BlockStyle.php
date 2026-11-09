<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Style of a CMS block: a map property => value next to the props ({type, props, style}).
 * Never CSS: each property takes a named value of the charte (Charte) or, for colours, title
 * size and spacings only, a bounded free value (#rrggbb, px). A free value is a deviation
 * (deviations()), allowed or refused by the builder level of the author (BuilderLevel).
 *
 * normalize() refuses with InvalidPageBlocks anything malformed whatever the level (unknown
 * property, value that is not a string, null included, value outside its format or bounds), then
 * with StyleNotAllowed a well formed free value when the level does not allow it. A free value
 * equal to a charte value becomes its key (#82cec8 => teal, 32px => m), so it follows the charte
 * and is not a deviation.
 */
final class BlockStyle
{
    public const KIND_COLOR = 'couleur';

    public const KIND_TITLE_SIZE = 'taille-titre';

    public const KIND_SPACING = 'espacement';

    public const KIND_CHOICE = 'choix';

    public const KIND_ANCHOR = 'ancre';

    /** Property => [label, kind], in the order of the normalized style. */
    public const PROPERTIES = [
        'fond' => ['Fond', self::KIND_COLOR],
        'texte' => ['Texte', self::KIND_COLOR],
        'accent' => ['Accent', self::KIND_COLOR],
        'titre' => ['Taille du titre', self::KIND_TITLE_SIZE],
        'hautEspace' => ['Espace au-dessus', self::KIND_SPACING],
        'basEspace' => ['Espace en dessous', self::KIND_SPACING],
        'largeur' => ['Largeur', self::KIND_CHOICE],
        'alignement' => ['Alignement', self::KIND_CHOICE],
        'arrondi' => ['Arrondi', self::KIND_CHOICE],
        'masquerMobile' => ['Masquer sur mobile', self::KIND_CHOICE],
        'masquerOrdinateur' => ['Masquer sur ordinateur', self::KIND_CHOICE],
        'ancre' => ['Ancre', self::KIND_ANCHOR],
    ];

    public const WIDTHS = ['contenu', 'large', 'pleine'];

    public const ALIGNMENTS = ['gauche', 'centre', 'droite'];

    public const YES_NO = ['oui', 'non'];

    /** Bounds of a free title size, in px. */
    public const TITLE_MIN = 14;

    public const TITLE_MAX = 96;

    /** Bounds of a free spacing, in px. */
    public const SPACING_MIN = 0;

    public const SPACING_MAX = 200;

    /** PCRE patterns; D: `$` refuses a trailing newline. */
    public const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/D';

    public const PX_PATTERN = '/^(0|[1-9]\d{0,2})px$/D';

    public const ANCHOR_PATTERN = '/^[a-z0-9-]{1,40}$/D';

    /**
     * Validated style, properties in the order of PROPERTIES; an empty value means « not set »
     * and is dropped. $block is where the block is in the content (its position from 1 at the root,
     * or its BlockPath), for messages. $type restricts the properties to BlockType::styleProperties()
     * (a container has no accent, title size, alignment nor radius); null accepts them all.
     *
     * @return array<string, string>
     *
     * @throws InvalidPageBlocks malformed style, whatever the level
     * @throws StyleNotAllowed   free value and the level does not allow it
     */
    public static function normalize(mixed $style, BuilderLevel $level = BuilderLevel::Libre, int|BlockPath $block = 1, ?BlockType $type = null): array
    {
        $path = $block instanceof BlockPath ? $block : BlockPath::root($block);
        $where = $path->label();
        if (null === $style || [] === $style) {
            return [];
        }
        if (!is_array($style) || array_is_list($style)) {
            throw new InvalidPageBlocks(sprintf('%s : style invalide.', $where));
        }

        $unknown = array_diff(array_map('strval', array_keys($style)), $type?->styleProperties() ?? array_keys(self::PROPERTIES));
        if ([] !== $unknown) {
            throw new InvalidPageBlocks(sprintf('%s : réglage(s) de style non autorisé(s) : %s.', $where, implode(', ', $unknown)));
        }

        $normalized = [];
        foreach (array_keys(self::PROPERTIES) as $property) {
            $value = array_key_exists($property, $style) ? $style[$property] : '';
            if (!is_string($value)) {
                throw new InvalidPageBlocks(sprintf('%s : valeur invalide pour « %s ».', $where, self::label($property)));
            }
            if ('' !== $value) {
                $normalized[$property] = self::normalizeValue($property, $value, $where);
            }
        }

        if (!$level->allowsFreeValues()) {
            $deviation = self::deviations($normalized, $path, $type->value ?? '')[0] ?? null;
            if (null !== $deviation) {
                throw new StyleNotAllowed($deviation);
            }
        }

        return $normalized;
    }

    /**
     * One deviation per free value (colour, title size or spacing outside the charte), none for
     * a charte value. Reads a stored style as is and never fails on it: a style that is not a map,
     * or a value that is not a string (number, null, nested array), is ignored; a malformed string
     * (`red`) is a deviation, to be corrected in the builder.
     *
     * @param mixed  $style normalized or stored style
     * @param string $type  type of the block holding the style, as stored ('' when unknown)
     *
     * @return list<StyleDeviation>
     */
    public static function deviations(mixed $style, int|BlockPath $block = 1, string $type = ''): array
    {
        if (!is_array($style)) {
            return [];
        }

        $path = $block instanceof BlockPath ? $block : BlockPath::root($block);
        $deviations = [];
        foreach (array_keys(self::PROPERTIES) as $property) {
            $value = $style[$property] ?? '';
            if (is_string($value) && '' !== $value && !self::isCharteValue(self::kind($property), $value)) {
                $deviations[] = new StyleDeviation($path->root, $property, $value, $path->label(), $type);
            }
        }

        return $deviations;
    }

    /** French label of a property (the property itself when unknown). */
    public static function label(string $property): string
    {
        return self::PROPERTIES[$property][0] ?? $property;
    }

    /** Kind of a property (KIND_*), KIND_CHOICE when unknown. */
    public static function kind(string $property): string
    {
        return self::PROPERTIES[$property][1] ?? self::KIND_CHOICE;
    }

    /**
     * Allowed values of a KIND_CHOICE property, empty list for the other kinds.
     *
     * @return list<string>
     */
    public static function choices(string $property): array
    {
        return match ($property) {
            'largeur' => self::WIDTHS,
            'alignement' => self::ALIGNMENTS,
            'arrondi' => array_keys(Charte::RADII),
            'masquerMobile', 'masquerOrdinateur' => self::YES_NO,
            default => [],
        };
    }

    private static function isCharteValue(string $kind, string $value): bool
    {
        return match ($kind) {
            self::KIND_COLOR => array_key_exists($value, Charte::COLORS),
            self::KIND_TITLE_SIZE => array_key_exists($value, Charte::TITLE_SIZES),
            self::KIND_SPACING => array_key_exists($value, Charte::SPACINGS),
            default => true,
        };
    }

    /**
     * @throws InvalidPageBlocks
     */
    private static function normalizeValue(string $property, string $value, string $where): string
    {
        $kind = self::kind($property);
        if (self::KIND_CHOICE !== $kind && self::KIND_ANCHOR !== $kind && self::isCharteValue($kind, $value)) {
            return $value;
        }

        return match ($kind) {
            self::KIND_COLOR => self::freeColor($property, $value, $where),
            self::KIND_TITLE_SIZE => self::freeSize($value, Charte::TITLE_SIZES, self::TITLE_MIN, self::TITLE_MAX)
                ?? throw new InvalidPageBlocks(sprintf('%s : « %s » n\'est pas une taille de titre valide (une taille de la charte, ou de %dpx à %dpx).', $where, $value, self::TITLE_MIN, self::TITLE_MAX)),
            self::KIND_SPACING => self::freeSize($value, Charte::SPACINGS, self::SPACING_MIN, self::SPACING_MAX)
                ?? throw new InvalidPageBlocks(sprintf('%s : « %s » n\'est pas un espacement valide pour « %s » (un espacement de la charte, ou de %dpx à %dpx).', $where, $value, self::label($property), self::SPACING_MIN, self::SPACING_MAX)),
            self::KIND_ANCHOR => 1 === preg_match(self::ANCHOR_PATTERN, $value)
                ? $value
                : throw new InvalidPageBlocks(sprintf('%s : « %s » n\'est pas une ancre valide (1 à 40 caractères : minuscules, chiffres, tiret).', $where, $value)),
            default => in_array($value, self::choices($property), true)
                ? $value
                : throw new InvalidPageBlocks(sprintf('%s : « %s » n\'est pas une valeur autorisée pour « %s ».', $where, $value, self::label($property))),
        };
    }

    /**
     * @throws InvalidPageBlocks
     */
    private static function freeColor(string $property, string $value, string $where): string
    {
        if (1 !== preg_match(self::COLOR_PATTERN, $value)) {
            throw new InvalidPageBlocks(sprintf('%s : « %s » n\'est pas une couleur valide pour « %s » (une couleur de la charte, ou #rrggbb).', $where, $value, self::label($property)));
        }

        return Charte::colorKey($value) ?? strtolower($value);
    }

    /**
     * Free px value within bounds, replaced by the charte key of the same value; null when malformed or out of bounds.
     *
     * @param array<array-key, array{string, string}> $charte
     */
    private static function freeSize(string $value, array $charte, int $min, int $max): ?string
    {
        if (1 !== preg_match(self::PX_PATTERN, $value, $match) || (int) $match[1] < $min || (int) $match[1] > $max) {
            return null;
        }
        foreach ($charte as $key => [, $px]) {
            if ($px === $value) {
                return (string) $key;
            }
        }

        return $value;
    }
}
