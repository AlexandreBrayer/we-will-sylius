<?php

declare(strict_types=1);

namespace App\Yack\Assistance\Domain;

/**
 * Référence d'appareil telle qu'elle est lue ou saisie (`read`), ramenée à sa forme canonique.
 *
 * Normalisation : majuscules, tirets Unicode ramenés à « - », espaces autour d'un tiret retirées,
 * libellé de tête retiré (« Model: », « Réf. »…), ponctuation finale retirée ; une espace avant un
 * suffixe connu devient un tiret (« SRK25ZS W » donne « SRK25ZS-W »), les autres espaces sont retirées.
 *
 * Découpage MHI quand le préfixe est connu : préfixe, puissance, série, suffixe (« SRK25ZS-WFT » :
 * SRK, 25, ZS, WFT). Si le découpage échoue, une correction des confusions de lecture est tentée
 * (O, I, L, S, B lus à la place de 0, 1, 1, 5, 8 dans la puissance, l'inverse dans le préfixe) :
 * la référence porte alors `corrected`. Une référence non découpable reste `structured: false`.
 */
final readonly class ModelReference
{
    public const MAX_LENGTH = 40;

    /** Préfixes MHI découpables, du plus long au plus court (FDCW avant FDC). */
    public const PREFIXES = ['FDCW', 'FDFU', 'FDFW', 'FDTC', 'FDTW', 'FDUH', 'FDUM', 'FDUT', 'FDC', 'FDE', 'FDF', 'FDK', 'FDT', 'FDU', 'HMU', 'SCM', 'SKM', 'SRC', 'SRF', 'SRK', 'SRR'];

    /** Suffixe connu : W et ses options (WF, WFT, W1…), A, E, S. */
    private const KNOWN_SUFFIX = '/^(W[A-Z0-9]{0,3}|A|E|S)$/';

    private const LABEL = '/^(MODÈLE|MODELE|MODEL|TYPE|RÉF\.?|REF\.?)\s*(:\s*|\s+)/u';

    private const SERIES_AND_SUFFIX = '/^([A-Z][A-Z0-9]*?)(?:-([A-Z0-9]+))?$/';

    /** Confusions de lecture dans la zone puissance (lettre lue, chiffre voulu). */
    private const LETTER_TO_DIGIT = ['O' => '0', 'I' => '1', 'L' => '1', 'S' => '5', 'B' => '8'];

    /** Confusions de lecture dans le préfixe (chiffre lu, lettres possibles). */
    private const DIGIT_TO_LETTERS = ['0' => ['O'], '1' => ['I', 'L'], '5' => ['S'], '8' => ['B']];

    private function __construct(
        public string $read,
        public string $canonical,
        public bool $structured,
        public bool $corrected,
        public ?string $prefix,
        public ?string $capacity,
        public ?string $series,
        public ?string $suffix,
    ) {
    }

    /** Référence saisie ou lue sur une plaque. Null si la saisie est vide (après normalisation) ou dépasse 40 caractères. */
    public static function fromInput(string $input): ?self
    {
        return self::build($input, true);
    }

    /** Modèle cité par un document : même normalisation et même découpage, sans correction de lecture. */
    public static function fromDocument(string $model): ?self
    {
        return self::build($model, false);
    }

    private static function build(string $input, bool $correctReading): ?self
    {
        $read = trim($input);
        if ('' === $read || mb_strlen($read) > self::MAX_LENGTH) {
            return null;
        }

        $normalized = self::normalize($read);
        if ('' === $normalized) {
            return null;
        }

        $parts = self::split($normalized);
        if (null !== $parts) {
            return new self($read, $normalized, true, false, ...$parts);
        }

        $parts = $correctReading ? self::splitWithCorrection($normalized) : null;
        if (null !== $parts) {
            return new self($read, self::assemble(...$parts), true, true, ...$parts);
        }

        return new self($read, $normalized, false, false, null, null, null, null);
    }

    /** Forme canonique sans correction de lecture ni découpage (règle partagée avec le générateur de la base). */
    public static function normalize(string $input): string
    {
        $text = mb_strtoupper(trim($input));
        $text = (string) preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $text);
        $text = trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text));
        $text = (string) preg_replace('/ ?- ?/', '-', $text);
        $text = (string) preg_replace(self::LABEL, '', $text);
        $text = trim((string) preg_replace('/[\s.,;:!?]+$/u', '', $text));

        $words = '' === $text ? [] : explode(' ', $text);
        if (\count($words) > 1 && 1 === preg_match(self::KNOWN_SUFFIX, $words[\count($words) - 1])) {
            $suffix = array_pop($words);

            return implode('', $words) . '-' . $suffix;
        }

        return implode('', $words);
    }

    /** Lettre de base du suffixe, avant les options et le chiffre (« W » pour WFT ou W1). */
    public function suffixBase(): ?string
    {
        return null === $this->suffix ? null : $this->suffix[0];
    }

    public function is(self $other): bool
    {
        return $this->canonical === $other->canonical;
    }

    /** Même préfixe, même puissance, même série ; le suffixe peut différer. */
    public function sameModelAs(self $other): bool
    {
        return $this->sameTypeAs($other) && $this->capacity === $other->capacity && $this->series === $other->series;
    }

    /** Même préfixe, même série, même suffixe de base ; la puissance peut différer. */
    public function sameSeriesAs(self $other): bool
    {
        return $this->sameTypeAs($other) && $this->series === $other->series && $this->suffixBase() === $other->suffixBase();
    }

    /** Même préfixe, donc même type d'appareil (muraux SRK, groupes SRC…). */
    public function sameTypeAs(self $other): bool
    {
        return $this->structured && $other->structured && $this->prefix === $other->prefix;
    }

    /** @return array{string, string, string, ?string}|null */
    private static function split(string $canonical): ?array
    {
        $pattern = '/^(' . implode('|', self::PREFIXES) . ')(\d{2,3})([A-Z][A-Z0-9]*?)(?:-([A-Z0-9]+))?$/';
        if (1 !== preg_match($pattern, $canonical, $match)) {
            return null;
        }

        return [$match[1], $match[2], $match[3], self::optional($match[4] ?? '')];
    }

    /** @return array{string, string, string, ?string}|null */
    private static function splitWithCorrection(string $canonical): ?array
    {
        foreach (self::PREFIXES as $prefix) {
            $length = \strlen($prefix);
            if (!self::readsAsPrefix(substr($canonical, 0, $length), $prefix)) {
                continue;
            }
            $rest = substr($canonical, $length);
            foreach ([3, 2] as $digits) {
                $capacity = strtr(substr($rest, 0, $digits), self::LETTER_TO_DIGIT);
                if (1 !== preg_match('/^\d{' . $digits . '}$/', $capacity)
                    || 1 !== preg_match(self::SERIES_AND_SUFFIX, substr($rest, $digits), $match)) {
                    continue;
                }

                return [$prefix, $capacity, $match[1], self::optional($match[2] ?? '')];
            }
        }

        return null;
    }

    private static function readsAsPrefix(string $read, string $prefix): bool
    {
        if (\strlen($read) !== \strlen($prefix)) {
            return false;
        }
        for ($i = 0, $n = \strlen($prefix); $i < $n; ++$i) {
            if ($read[$i] !== $prefix[$i] && !\in_array($prefix[$i], self::DIGIT_TO_LETTERS[$read[$i]] ?? [], true)) {
                return false;
            }
        }

        return true;
    }

    private static function optional(string $group): ?string
    {
        return '' === $group ? null : $group;
    }

    private static function assemble(string $prefix, string $capacity, string $series, ?string $suffix): string
    {
        return $prefix . $capacity . $series . (null === $suffix ? '' : '-' . $suffix);
    }
}
