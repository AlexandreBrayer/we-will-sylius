<?php

declare(strict_types=1);

namespace App\Yack\Documentation\Application;

/**
 * French text → search terms, in plain PHP: lower case without accents, French stop words
 * removed, plural "s"/"x" removed, words cut to 6 letters (a crude stemmer: "entretien" and
 * "entretenir", "liaison" and "liaisons" give the same term). Numbers are kept ("8", "e5").
 */
final class LexicalAnalyzer
{
    public const STEM_LENGTH = 6;

    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'œ' => 'oe', 'æ' => 'ae',
    ];

    /** Normalised form (no accent), checked before the plural and the cut. */
    private const STOP_WORDS = [
        'a', 'afin', 'ai', 'alors', 'au', 'aux', 'avec', 'avoir', 'bien', 'c', 'ca', 'ce', 'ceci', 'cela', 'ces', 'cet',
        'cette', 'combien', 'comment', 'd', 'dans', 'de', 'des', 'doit', 'doivent', 'donc', 'du', 'elle', 'elles', 'en',
        'est', 'et', 'etre', 'faire', 'faut', 'il', 'ils', 'j', 'je', 'l', 'la', 'le', 'les', 'leur', 'leurs', 'lui',
        'm', 'ma', 'mais', 'me', 'mes', 'mon', 'n', 'ne', 'nous', 'on', 'ou', 'par', 'pas', 'peut', 'peuvent', 'pour',
        'qu', 'quand', 'que', 'quel', 'quelle', 'quelles', 'quels', 'qui', 'quoi', 's', 'sa', 'se', 'ses', 'si', 'son',
        'sont', 'sur', 't', 'ta', 'te', 'tes', 'ton', 'tu', 'un', 'une', 'vos', 'votre', 'vous', 'y',
        'documentation', 'notice', 'notices', 'appareil',
    ];

    /** Units: terms that say "how much", never "what about" (with the numbers). */
    private const UNITS = ['bar', 'cm', 'db', 'hz', 'kg', 'kw', 'mbar', 'mm', 'w'];

    public static function normalize(string $text): string
    {
        return strtr(mb_strtolower($text), self::ACCENTS);
    }

    /**
     * Terms of the text, in order, duplicates kept (term frequency).
     *
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        $terms = [];
        foreach (preg_split('/[^a-z0-9]+/', self::normalize($text), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (\in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            if (ctype_digit($word)) {
                $terms[] = $word;
                continue;
            }
            if (\strlen($word) < 2) {
                continue;
            }
            if (\strlen($word) > 3 && !str_ends_with($word, 'ss') && (str_ends_with($word, 's') || str_ends_with($word, 'x'))) {
                $word = substr($word, 0, -1);
            }
            $terms[] = substr($word, 0, self::STEM_LENGTH);
        }

        return $terms;
    }

    /** A number or a unit: a term that cannot make a passage relevant on its own. */
    public static function isQuantity(string $term): bool
    {
        return ctype_digit($term) || \in_array($term, self::UNITS, true);
    }

    /** @return list<string> */
    public static function distinctTerms(string $text): array
    {
        return array_values(array_unique(self::terms($text)));
    }
}
