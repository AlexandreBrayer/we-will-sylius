<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

use App\Yack\Catalog\Domain\InvalidSourceProduct;

/**
 * A line of an imported file (or a rejected record corrected before a replay) written into a record of
 * the API feed, with the errors of its cells: an invalid cell is kept as typed, to be corrected.
 */
final readonly class ImportedLine
{
    /**
     * @param array<string, mixed> $record
     * @param list<string>         $errors
     */
    public function __construct(
        public array $record,
        public array $errors = [],
    ) {
    }

    /**
     * Writes each cell into the record, collecting the errors of the cells.
     *
     * @param array<string, mixed>              $record
     * @param list<array{ImportColumn, string}> $cells
     */
    public static function fill(array $record, array $cells): self
    {
        $errors = [];
        foreach ($cells as [$column, $cell]) {
            try {
                $column->put($record, $cell);
            } catch (InvalidSourceProduct $exception) {
                $column->write($record, '' === trim($cell) ? null : trim($cell));
                $errors[] = $exception->getMessage();
            }
        }

        return new self($record, $errors);
    }

    /** Code of the product, null when the record has none. */
    public function code(): ?string
    {
        $code = $this->record['sku'] ?? null;

        return is_string($code) && '' !== trim($code) ? trim($code) : null;
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    /** The errors in one sentence, separated by semicolons. */
    public function reason(): string
    {
        return implode(' ; ', $this->errors);
    }
}
