<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

/**
 * Columns of an imported file matched to the column contract (ImportColumn) by their header: a header
 * nobody knows is ignored, a column found twice is read from its first occurrence only. Turns each line
 * into a record of the API feed.
 */
final readonly class ColumnMapping
{
    /**
     * @param list<string>              $headers
     * @param array<int, ImportColumn>  $columns position in the line => column
     */
    private function __construct(
        public array $headers,
        public array $columns,
    ) {
    }

    /** @param list<string> $headers */
    public static function detect(array $headers): self
    {
        $columns = [];
        foreach ($headers as $position => $header) {
            $column = ImportColumn::fromHeader($header);
            if (null !== $column && !in_array($column, $columns, true)) {
                $columns[$position] = $column;
            }
        }

        return new self($headers, $columns);
    }

    /**
     * Required columns the file does not have: nothing can be imported.
     *
     * @return list<ImportColumn>
     */
    public function missing(): array
    {
        return array_values(array_filter(ImportColumn::cases(), fn (ImportColumn $column): bool => $column->required() && !in_array($column, $this->columns, true)));
    }

    /**
     * Optional columns the file does not have: the field is emptied on the products, as with a record of
     * the API feed without it.
     *
     * @return list<ImportColumn>
     */
    public function absent(): array
    {
        return array_values(array_filter(ImportColumn::cases(), fn (ImportColumn $column): bool => !$column->required() && !in_array($column, $this->columns, true)));
    }

    /**
     * Each header of the file and the column it was matched to, null when it is ignored.
     *
     * @return list<array{header: string, column: ImportColumn|null}>
     */
    public function pairs(): array
    {
        $pairs = [];
        foreach ($this->headers as $position => $header) {
            $pairs[] = ['header' => $header, 'column' => $this->columns[$position] ?? null];
        }

        return $pairs;
    }

    /**
     * The line as a record of the API feed, with the errors of its cells.
     *
     * @param list<string> $cells
     */
    public function line(array $cells): ImportedLine
    {
        $pairs = [];
        foreach ($this->columns as $position => $column) {
            $pairs[] = [$column, $cells[$position] ?? ''];
        }

        return ImportedLine::fill(['price' => ['currency' => 'EUR']], $pairs);
    }
}
