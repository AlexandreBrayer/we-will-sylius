<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Builder level rule on a write (D4). At a level that allows free values, everything passes. At
 * the `charte` level, a content keeps the free values (StyleDeviation) its stored version already
 * holds, but cannot add one.
 *
 * Deviations are compared as a multiset of (property, value) pairs, not by block position:
 *  - moving a block, deleting a block, inserting a block without free value: passes;
 *  - changing a free value, or putting the same value on another property: refused;
 *  - adding an occurrence (new value, duplicated block, copy of a template that holds one): refused,
 *    a `charte` account does not spread the free values set by a `libre` account.
 */
final class CharteGuard
{
    /**
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $blocks   content written (normalized)
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $previous stored version, [] for a new page or template
     *
     * @throws StyleNotAllowed the first new deviation, with its position in $blocks
     */
    public static function assertAllowed(BuilderLevel $level, array $blocks, array $previous = []): void
    {
        if ($level->allowsFreeValues()) {
            return;
        }

        $new = self::newDeviations($blocks, $previous);
        if ([] !== $new) {
            throw new StyleNotAllowed($new[0]);
        }
    }

    /**
     * Deviations of $blocks beyond those of $previous (same property and value, counted), in block order.
     *
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $blocks
     * @param list<array{type: string, props: array<string, string>, style?: array<string, string>}> $previous
     *
     * @return list<StyleDeviation>
     */
    public static function newDeviations(array $blocks, array $previous): array
    {
        $available = [];
        foreach (PageBlocks::deviations($previous) as $deviation) {
            $key = self::key($deviation);
            $available[$key] = ($available[$key] ?? 0) + 1;
        }

        $new = [];
        foreach (PageBlocks::deviations($blocks) as $deviation) {
            $key = self::key($deviation);
            if (($available[$key] ?? 0) > 0) {
                --$available[$key];
            } else {
                $new[] = $deviation;
            }
        }

        return $new;
    }

    private static function key(StyleDeviation $deviation): string
    {
        return $deviation->property . '=' . strtolower($deviation->value);
    }
}
