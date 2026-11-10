<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain\Import;

/** Port: reads the cells of an imported file (CSV or XLSX). */
interface SpreadsheetReader
{
    /**
     * @throws ImportFileRefused when the file cannot be read, has no header or too many lines
     */
    public function read(ImportFile $file): SpreadsheetRows;
}
