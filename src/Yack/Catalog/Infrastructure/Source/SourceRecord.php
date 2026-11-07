<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Source;

use App\Yack\Catalog\Domain\InvalidSourceProduct;

/**
 * Typed reading of a record decoded from a Yack JSON export: every value of the export is
 * `mixed`, each reader checks the type and raises InvalidSourceProduct instead of casting blindly.
 */
final readonly class SourceRecord
{
    /** @param array<mixed> $data */
    public function __construct(private array $data)
    {
    }

    /** A record that is not an object (list item of another type) is read as an empty record. */
    public static function from(mixed $record): self
    {
        return new self(is_array($record) ? $record : []);
    }

    public function string(string $key): string
    {
        return $this->optionalString($key) ?? throw new InvalidSourceProduct(sprintf('Field "%s" is missing or empty.', $key));
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /** Whole number; a missing value is $default, a numeric string or a float is truncated. */
    public function int(string $key, int $default = 0): int
    {
        $value = $this->data[$key] ?? null;

        if (null === $value) {
            return $default;
        }
        if (!is_numeric($value)) {
            throw new InvalidSourceProduct(sprintf('Field "%s" is not a number (%s).', $key, self::describe($value)));
        }

        return is_int($value) ? $value : (int) (float) $value;
    }

    /** Decimal number given as a number or a numeric string ("6490.00"), null when missing or not numeric. */
    public function number(string $key): ?float
    {
        $value = $this->data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /** Nested object (e.g. "price"), null when missing or not an object. */
    public function record(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) ? new self($value) : null;
    }

    /**
     * Key/value pairs of a nested object (e.g. "specs"), each value a string or a number.
     *
     * @return array<string, string|int|float>
     */
    public function scalars(string $key): array
    {
        $value = $this->data[$key] ?? [];
        if (!is_array($value)) {
            throw new InvalidSourceProduct(sprintf('Field "%s" is not an object.', $key));
        }

        $scalars = [];
        foreach ($value as $name => $item) {
            if (is_bool($item)) {
                $item = $item ? '1' : '';
            }
            if (!is_int($item) && !is_float($item) && !is_string($item)) {
                throw new InvalidSourceProduct(sprintf('Field "%s.%s" is not a scalar (%s).', $key, $name, self::describe($item)));
            }
            $scalars[(string) $name] = $item;
        }

        return $scalars;
    }

    /**
     * List of texts (e.g. "sectors"), blank items dropped; a missing value is an empty list.
     *
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->data[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidSourceProduct(sprintf('Field "%s" is not a list.', $key));
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidSourceProduct(sprintf('Field "%s" holds something else than a text (%s).', $key, self::describe($item)));
            }
            if ('' !== trim($item)) {
                $strings[] = trim($item);
            }
        }

        return $strings;
    }

    /** Yes/no value given as a JSON boolean, null when missing. */
    public function optionalBool(string $key): ?bool
    {
        $value = $this->data[$key] ?? null;
        if (null !== $value && !is_bool($value)) {
            throw new InvalidSourceProduct(sprintf('Field "%s" is not a boolean (%s).', $key, self::describe($value)));
        }

        return $value;
    }

    /** Raw value as text, for error messages ("" when missing). */
    public function text(string $key): string
    {
        $value = $this->data[$key] ?? '';

        return is_scalar($value) ? (string) $value : self::describe($value);
    }

    private static function describe(mixed $value): string
    {
        return is_scalar($value) ? var_export($value, true) : get_debug_type($value);
    }
}
