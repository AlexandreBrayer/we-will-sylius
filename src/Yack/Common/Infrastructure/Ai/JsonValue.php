<?php

declare(strict_types=1);

namespace App\Yack\Common\Infrastructure\Ai;

/**
 * Reads the decoded JSON a model provider returns without trusting its shape: a missing key or a
 * value of another type gives null or an empty list, never a PHP error.
 */
final class JsonValue
{
    /** Value at `$path` (keys and list indexes), null as soon as a step is missing. */
    public static function at(mixed $value, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * The value when it is a JSON object, with its named keys; null otherwise.
     *
     * @return array<string, mixed>|null
     */
    public static function object(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $object = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }

    /**
     * The items of a JSON array; an empty list for anything else.
     *
     * @return list<mixed>
     */
    public static function items(mixed $value): array
    {
        return \is_array($value) ? array_values($value) : [];
    }
}
