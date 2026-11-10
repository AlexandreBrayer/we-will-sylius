<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

/**
 * Cells of an imported file: the header line and the following lines, by line number (the header is
 * line 1, empty lines are left out), with what was detected to read a CSV file.
 */
final readonly class SpreadsheetRows
{
    /**
     * @param list<string>             $headers
     * @param array<int, list<string>> $rows      line number => cells
     * @param string|null              $encoding  encoding of a CSV file (UTF-8, Windows-1252)
     * @param string|null              $delimiter separator of a CSV file
     */
    public function __construct(
        public array $headers,
        public array $rows,
        public ?string $encoding = null,
        public ?string $delimiter = null,
    ) {
    }

    /** Separator as shown in the preview. */
    public function delimiterLabel(): ?string
    {
        return match ($this->delimiter) {
            null => null,
            ';' => 'point-virgule',
            ',' => 'virgule',
            "\t" => 'tabulation',
            default => $this->delimiter,
        };
    }
}
