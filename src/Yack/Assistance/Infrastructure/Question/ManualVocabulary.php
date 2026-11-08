<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Infrastructure\Question;

/**
 * Ajustements du texte pour la récupération lexicale des manuels (BM25 de Documentation, analyse en français) :
 *
 * - codes d'erreur : les manuels MHI écrivent « E 5 », « E 16 » avec une espace ; l'analyse jette la lettre seule
 *   et prend le nombre pour une quantité. Une lettre isolée (sauf « a », « à », « y », mots français) suivie de 1 à
 *   3 chiffres est accolée (`E 16` donne `E16`), dans les passages comme dans la question. Une question qui cite
 *   un code est aussi cherchée par ses seuls codes (`codes`) : le passage qui nomme le code passe avant ceux qui
 *   répètent « error code » ;
 * - manuel en anglais : la question française est aussi cherchée sous une forme anglaise, faite des termes
 *   métier traduits par la table ci-dessous et des mots gardés tels quels (codes, nombres, mots en capitales
 *   comme RUN ou TIMER). Les autres mots français sont retirés de cette forme : ils n'existent pas dans le texte
 *   anglais et feraient échouer la règle de pertinence (la moitié des mots de la question dans le passage).
 */
final class ManualVocabulary
{
    /** Terme français (minuscules, sans accent) → termes anglais des manuels MHI. */
    public const FRENCH_TO_ENGLISH = [
        'alimentation' => 'power source',
        'capteur' => 'sensor',
        'carte' => 'pcb',
        'chauffage' => 'heating',
        'clignote' => 'flash flashing blink',
        'clignotement' => 'flash flashing blink',
        'clignotent' => 'flash flashing blink',
        'compresseur' => 'compressor',
        'denivele' => 'height difference',
        'erreur' => 'error',
        'exterieure' => 'outdoor',
        'filtre' => 'filter',
        'fusible' => 'fuse',
        'interieure' => 'indoor',
        'liaison' => 'line piping',
        'longueur' => 'length',
        'maximale' => 'max maximum',
        'maximum' => 'max maximum',
        'moteur' => 'motor',
        'nettoyer' => 'clean cleaning',
        'nettoyage' => 'clean cleaning',
        'refroidissement' => 'cooling',
        'sonde' => 'sensor',
        'telecommande' => 'remote control',
        'tension' => 'voltage',
        'ventilateur' => 'fan',
        'voyant' => 'lamp light',
    ];

    private const ACCENTS = ['à' => 'a', 'â' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u'];

    /** `E 16` → `E16` (lettre isolée suivie d'un nombre de 1 à 3 chiffres ; « a », « à » et « y » restent des mots). */
    public static function glueCodes(string $text): string
    {
        return (string) preg_replace('/(?<![\p{L}\p{N}\'’])(?![aAàÀyY]\s)(\p{L})\s+(\d{1,3})(?![\p{N}\p{L}])/u', '$1$2', $text);
    }

    /**
     * Autres formulations de la question pour la récupération : ses seuls codes, puis, si un manuel lu est en
     * anglais, sa forme anglaise. Vide si aucune ne s'applique.
     *
     * @return list<string>
     */
    public static function rewordings(string $question, bool $englishManual): array
    {
        return array_values(array_filter([self::codes($question), $englishManual ? self::english($question) : null]));
    }

    /**
     * Codes cités par la question (`E16`, `E5`, après accolement), en question courte (« E5 ? » : une question a 3
     * caractères au moins) ; null s'il n'y en a pas.
     */
    public static function codes(string $question): ?string
    {
        preg_match_all('/(?<![\p{L}\p{N}])\p{L}{1,2}\d{1,3}(?![\p{L}\p{N}])/u', self::glueCodes($question), $matches);

        return [] === $matches[0] ? null : implode(' ', array_unique($matches[0])) . ' ?';
    }

    /**
     * Forme anglaise de la question pour un manuel en anglais ; null si aucun terme de la table n'y figure.
     */
    public static function english(string $question): ?string
    {
        $kept = [];
        $translated = false;
        foreach (preg_split('/[^\p{L}\p{N}]+/u', self::glueCodes($question), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $key = self::key($word);
            if (isset(self::FRENCH_TO_ENGLISH[$key])) {
                $kept[] = self::FRENCH_TO_ENGLISH[$key];
                $translated = true;
            } elseif (1 === preg_match('/\p{N}/u', $word) || (mb_strlen($word) >= 2 && mb_strtoupper($word) === $word)) {
                $kept[] = $word;
            }
        }

        return $translated ? implode(' ', $kept) : null;
    }

    /** Minuscules sans accent, pluriel en « s » ou « x » retiré. */
    private static function key(string $word): string
    {
        $key = strtr(mb_strtolower($word), self::ACCENTS);

        return \strlen($key) > 3 && (str_ends_with($key, 's') || str_ends_with($key, 'x')) ? substr($key, 0, -1) : $key;
    }
}
