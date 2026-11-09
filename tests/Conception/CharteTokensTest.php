<?php

declare(strict_types=1);

namespace App\Tests\Conception;

use App\Yack\Content\Domain\Charte;
use PHPUnit\Framework\TestCase;

/**
 * La charte du builder (Content\Domain\Charte) et la SCSS de la boutique (_tokens.scss) disent les mêmes valeurs :
 * chaque couleur `clé` vaut le token `$yack-clé`, chaque rayon vaut son token (Charte::RADIUS_TOKENS).
 *
 * @internal
 */
final class CharteTokensTest extends TestCase
{
    private const TOKENS = 'assets/shop/styles/_tokens.scss';

    public function testEveryCharteColourHasTheValueOfItsToken(): void
    {
        $tokens = self::tokens((string) file_get_contents(Rules::root() . '/' . self::TOKENS));

        $mismatches = [];
        foreach (Charte::COLORS as $key => [, $value]) {
            $token = self::hex($tokens['yack-' . $key] ?? 'absent');
            if ($token !== $value) {
                $mismatches[] = sprintf('%s : charte %s, $yack-%s %s', $key, $value, $key, $token);
            }
        }

        $this->assertSame([], $mismatches, 'Aligner Charte::COLORS sur _tokens.scss (la SCSS fait foi pour le rendu).');
    }

    public function testEveryCharteRadiusHasTheValueOfItsToken(): void
    {
        $tokens = self::tokens((string) file_get_contents(Rules::root() . '/' . self::TOKENS));

        $mismatches = [];
        foreach (Charte::RADIUS_TOKENS as $key => $token) {
            $value = Charte::RADII[$key][1];
            if (($tokens[$token] ?? 'absent') !== $value) {
                $mismatches[] = sprintf('%s : charte %s, $%s %s', $key, $value, $token, $tokens[$token] ?? 'absent');
            }
        }

        $this->assertSame([], $mismatches, 'Aligner Charte::RADII sur _tokens.scss.');
    }

    public function testTheRuleBites(): void
    {
        $tokens = self::tokens("// Couleurs\n\$yack-white: #FFF; // Blanc\n\$yack-teal: #82cec8;\n  \$yack-ignored: #000;\n");

        $this->assertSame(['yack-white' => '#FFF', 'yack-teal' => '#82cec8'], $tokens);
        $this->assertSame('#ffffff', self::hex($tokens['yack-white']));
        $this->assertNotSame('#ffffff', self::hex('#fffffe'));
    }

    /**
     * Top-level SCSS variables of a file: name (without $) => raw value.
     *
     * @return array<string, string>
     */
    private static function tokens(string $scss): array
    {
        preg_match_all('/^\$([a-z0-9-]+):\s*([^;]+);/m', $scss, $matches, \PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as [, $name, $value]) {
            $tokens[$name] = trim($value);
        }

        return $tokens;
    }

    /** #rgb or #rrggbb in lower case #rrggbb; anything else unchanged. */
    private static function hex(string $value): string
    {
        if (1 === preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $short)) {
            $value = '#' . $short[1] . $short[1] . $short[2] . $short[2] . $short[3] . $short[3];
        }

        return 1 === preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : $value;
    }
}
