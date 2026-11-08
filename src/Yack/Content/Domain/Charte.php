<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Yack charte as named values a block style can use (BlockStyle): colours, title sizes,
 * spacings and radii, each key => [label, CSS value]. Same values as
 * assets/shop/styles/_tokens.scss (colour key `magenta` = token `$yack-magenta`, checked by
 * tests/Conception/CharteTokensTest.php). The builder receives this catalog from the API
 * (toArray()), it never copies it.
 */
final class Charte
{
    /** Colour key => [label, #rrggbb]; the key is the name of the `$yack-<key>` token. */
    public const COLORS = [
        'magenta' => ['Magenta', '#a33c87'],
        'magenta-dark' => ['Magenta foncé', '#7e2c68'],
        'plum' => ['Prune', '#3d1633'],
        'teal' => ['Turquoise', '#82cec8'],
        'teal-dark' => ['Turquoise foncé', '#6bbdb6'],
        'teal-deep' => ['Turquoise profond', '#13605a'],
        'yellow' => ['Jaune', '#f5efb3'],
        'ochre' => ['Ocre', '#cf8b4f'],
        'dark' => ['Gris foncé', '#2b2b2e'],
        'gray' => ['Gris', '#4d4d4f'],
        'light' => ['Gris clair', '#f6f4f5'],
        'white' => ['Blanc', '#ffffff'],
        'black' => ['Noir', '#000000'],
    ];

    /** Title size key => [label, px]. */
    public const TITLE_SIZES = [
        's' => ['Petit', '24px'],
        'm' => ['Moyen', '32px'],
        'l' => ['Grand', '40px'],
        'xl' => ['Très grand', '56px'],
    ];

    /** Spacing key => [label, px]. */
    public const SPACINGS = [
        '0' => ['Aucun', '0px'],
        's' => ['Petit', '16px'],
        'm' => ['Moyen', '32px'],
        'l' => ['Grand', '48px'],
        'xl' => ['Très grand', '80px'],
    ];

    /** Radius key => [label, CSS value]; the values are the radius tokens of _tokens.scss (RADIUS_TOKENS). */
    public const RADII = [
        'aucun' => ['Aucun', '0'],
        'petit' => ['Petit', '0.5rem'],
        'moyen' => ['Moyen', '12px'],
        'grand' => ['Grand', '1rem'],
    ];

    /** Radius key => token of _tokens.scss holding the same value (aucun has none). */
    public const RADIUS_TOKENS = [
        'petit' => 'yack-radius-sm',
        'moyen' => 'yack-radius-card',
        'grand' => 'yack-radius-lg',
    ];

    /**
     * The whole charte for the builder: one list of {key, label, value} per family.
     *
     * @return array{colors: list<array{key: string, label: string, value: string}>, titleSizes: list<array{key: string, label: string, value: string}>, spacings: list<array{key: string, label: string, value: string}>, radii: list<array{key: string, label: string, value: string}>}
     */
    public static function toArray(): array
    {
        return [
            'colors' => self::entries(self::COLORS),
            'titleSizes' => self::entries(self::TITLE_SIZES),
            'spacings' => self::entries(self::SPACINGS),
            'radii' => self::entries(self::RADII),
        ];
    }

    /** Key of the charte colour with this #rrggbb value (case ignored), null when the colour is outside the charte. */
    public static function colorKey(string $hex): ?string
    {
        foreach (self::COLORS as $key => [, $value]) {
            if ($value === strtolower($hex)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<array-key, array{string, string}> $family keys are strings, except the spacing `0` (an int key for PHP)
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    private static function entries(array $family): array
    {
        $entries = [];
        foreach ($family as $key => [$label, $value]) {
            $entries[] = ['key' => (string) $key, 'label' => $label, 'value' => $value];
        }

        return $entries;
    }
}
