<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * Trade vocabulary of HVAC customers: words and abbreviations that mean the same thing in a
 * search (an installer types "pac", the catalogue says "pompe à chaleur"). Each group is a set
 * of equivalent expressions, written in lower case.
 */
final class SearchVocabulary
{
    /** @var list<list<string>> */
    public const SYNONYMS = [
        // Yack range the climatiseurs (muraux, consoles, cassettes…) under "Pompe à Chaleur Air/Air".
        ['clim', 'climatisation', 'climatiseur', 'climatiseurs', 'pompe à chaleur air/air'],
        ['pac', 'pompe à chaleur', 'pompes à chaleur'],
        ['ecs', 'eau chaude sanitaire'],
        ['drv', 'vrf', 'vrv'],
        ['ue', 'unité extérieure', 'groupe extérieur'],
        ['ui', 'unité intérieure'],
        ['gainable', 'gainables'],
        ['ballon', 'ballons', 'chauffe-eau'],
        ['thermostat', 'régulation'],
        ['mural', 'murale', 'muraux', 'murales'],
        ['cassette', 'cassettes'],
        ['multisplit', 'multi-split'],
    ];

    private const ACCENTS = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae',
    ];

    /**
     * Expressions equivalent to a word (the word itself excluded), whatever its case and accents.
     *
     * @return list<string>
     */
    public static function synonymsOf(string $word): array
    {
        $key = self::fold($word);
        foreach (self::SYNONYMS as $group) {
            foreach ($group as $expression) {
                if (self::fold($expression) === $key) {
                    return array_values(array_filter($group, static fn (string $other): bool => self::fold($other) !== $key));
                }
            }
        }

        return [];
    }

    /** Lower case without accents: "Unité" and "unite" are the same word. */
    public static function fold(string $text): string
    {
        return strtr(mb_strtolower(trim($text)), self::ACCENTS);
    }
}
