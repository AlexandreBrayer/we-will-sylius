<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Value of a link prop of a block (BlockType::linkProps()). Same targets as resolveLink() of the shop
 * (front/src/lib/blocks/parse.ts) and as the link field of the builder (front/src/lib/editor/traits/link.ts):
 *  - `page:<slug>`, slug in the format of the CMS pages (CmsPage::$slug);
 *  - a path of the site, starting with a single `/` (`//host` and `/\host` leave the site);
 *  - `http://` or `https://` followed by a host;
 *  - `mailto:` followed by an address;
 *  - `tel:` followed by a number (digits, `+`, ASCII spaces, dots, hyphens, parentheses).
 *
 * Anything else is refused: other schemes (`javascript:`, `data:`, `vbscript:`), whitespace or control
 * characters anywhere (in front of the value too), character references (`&#106;`, `&colon;`), which a
 * renderer could decode into one of them. The scheme is case insensitive, as in a browser, so
 * `JaVaScRiPt:` is refused like `javascript:` and `HTTPS://` is accepted like `https://`.
 */
final class BlockLink
{
    public const MAX_LENGTH = 2000;

    /** Slug of a CMS page (same pattern as CmsPage::$slug). */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    /** Host of an http(s) link: optional user info, a name or an IPv6 address in brackets, optional port. */
    private const WEB_PATTERN = '~^https?://(?:[^/\\\\?#@]*@)?(?:\[[0-9a-f:.]+\]|[^/\\\\?#@:\[\]<>^|%"]+)(?::[0-9]{1,5})?(?:[/?#].*)?$~iDu';

    private const TEL_PATTERN = '/^tel:(?=[^0-9]*[0-9])[0-9+ .()\-]+$/iD';

    /** Whitespace, control and format characters (zero width, bidirectional marks). */
    private const BLANK_PATTERN = '/[\s\p{Z}\p{Cc}\p{Cf}]/u';

    /** Numeric or named character reference. */
    private const REFERENCE_PATTERN = '/&(?:#[0-9]+|#x[0-9a-f]+|[a-z][a-z0-9]*);/i';

    /** Whether $link is a target the shop renders; the empty value (no link) is allowed. */
    public static function isAllowed(string $link): bool
    {
        if ('' === $link) {
            return true;
        }
        if (!mb_check_encoding($link, 'UTF-8') || mb_strlen($link) > self::MAX_LENGTH || 1 === preg_match(self::REFERENCE_PATTERN, $link)) {
            return false;
        }
        if (1 === preg_match('/^tel:/i', $link)) {
            return 1 === preg_match(self::TEL_PATTERN, $link);
        }
        if (0 !== preg_match(self::BLANK_PATTERN, $link)) {
            return false;
        }
        if (str_starts_with($link, 'page:')) {
            return 1 === preg_match(self::SLUG_PATTERN, substr($link, 5));
        }
        if (str_starts_with($link, '/')) {
            return !in_array($link[1] ?? '', ['/', '\\'], true);
        }
        if (1 === preg_match('/^https?:/i', $link)) {
            return 1 === preg_match(self::WEB_PATTERN, $link);
        }

        return 1 === preg_match('/^mailto:./i', $link);
    }

    /**
     * Links of « Libellé | lien » lines (documents block), read as the shop reads them (parseFaq() and
     * documentLink() of front/src/lib/blocks/parse.ts): one element per line (`\n` or `\r\n`), the link is
     * what follows the first `|`, trimmed of the characters JavaScript's trim() removes. A line without
     * `|` has no link ('').
     *
     * @return list<string>
     */
    public static function inLines(string $value): array
    {
        $links = [];
        foreach (preg_split('/\r?\n/', $value) ?: [] as $line) {
            $parts = explode('|', $line, 2);
            $links[] = self::trim($parts[1] ?? '');
        }

        return $links;
    }

    /** The link as shown in a message: valid UTF-8, without control characters, 80 characters at most. */
    public static function quote(string $link): string
    {
        $text = (string) preg_replace('/[\p{Cc}\p{Cf}]/u', '?', mb_scrub($link, 'UTF-8'));

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 79) . '…' : $text;
    }

    /** Same characters as String.prototype.trim(): whitespace, separators, line terminators, U+FEFF. */
    private static function trim(string $text): string
    {
        return preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $text) ?? $text;
    }
}
