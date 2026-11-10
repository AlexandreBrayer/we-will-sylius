<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Import;

use App\Yack\Catalog\Domain\Import\ImportFile;
use App\Yack\Catalog\Domain\Import\ImportFileRefused;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use App\Yack\Catalog\Domain\Import\SpreadsheetReader;
use App\Yack\Catalog\Domain\Import\SpreadsheetRows;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Adapter: cells of an imported file. CSV as spreadsheets and ERPs export it: UTF-8 (with or without
 * BOM) or Windows-1252, separated by semicolons, commas or tabs (the one the header line uses most),
 * quoted fields over several lines. XLSX (first sheet) read with OpenSpout (ADR 0010), streamed.
 */
#[AsAlias(SpreadsheetReader::class)]
final readonly class SpreadsheetFileReader implements SpreadsheetReader
{
    private const DELIMITERS = [';', ',', "\t"];

    private const UTF8_BOM = "\xEF\xBB\xBF";

    public function read(ImportFile $file): SpreadsheetRows
    {
        return ImportFormat::Csv === $file->format ? $this->csv($file->path) : $this->xlsx($file->path);
    }

    private function csv(string $path): SpreadsheetRows
    {
        $content = @file_get_contents($path);
        if (false === $content) {
            throw new ImportFileRefused('Le fichier ne peut pas être lu.');
        }
        if (str_starts_with($content, self::UTF8_BOM)) {
            $content = substr($content, 3);
        }
        $encoding = 'UTF-8';
        if (!mb_check_encoding($content, 'UTF-8')) {
            $encoding = 'Windows-1252';
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $delimiter = self::delimiter(strtok($content, "\r\n") ?: '');

        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new ImportFileRefused('Le fichier ne peut pas être lu.');
        }
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        $number = 0;
        while (false !== ($cells = fgetcsv($stream, null, $delimiter, '"', ''))) {
            ++$number;
            $rows[$number] = array_map(static fn (?string $cell): string => (string) $cell, $cells);
        }
        fclose($stream);

        return $this->rows($rows, $encoding, $delimiter);
    }

    private function xlsx(string $path): SpreadsheetRows
    {
        $options = new Options();
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $rows = [];

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                $number = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[++$number] = array_map(self::cell(...), $row->toArray());
                    if (count($rows) > ImportFile::MAX_ROWS + 1) {
                        break;
                    }
                }

                break;
            }
        } catch (OpenSpoutException) {
            throw new ImportFileRefused('Le fichier XLSX ne peut pas être lu : est-il bien enregistré au format Excel (.xlsx) ?');
        } finally {
            $reader->close();
        }

        return $this->rows($rows, null, null);
    }

    /**
     * Header (first line that is not empty) and the following lines that are not empty.
     *
     * @param array<int, list<string>> $rows
     */
    private function rows(array $rows, ?string $encoding, ?string $delimiter): SpreadsheetRows
    {
        $rows = array_filter($rows, static fn (array $cells): bool => '' !== trim(implode('', $cells)));
        if ([] === $rows) {
            throw new ImportFileRefused('Le fichier ne contient aucune ligne : la première ligne doit porter les en-têtes de colonnes.');
        }
        $headerLine = array_key_first($rows);
        $headers = array_map(trim(...), $rows[$headerLine]);
        unset($rows[$headerLine]);
        if (count($rows) > ImportFile::MAX_ROWS) {
            throw new ImportFileRefused(sprintf('Trop de lignes : %d au maximum par fichier.', ImportFile::MAX_ROWS));
        }

        return new SpreadsheetRows($headers, $rows, $encoding, $delimiter);
    }

    /** Separator the header line uses most, semicolon when it uses none. */
    private static function delimiter(string $header): string
    {
        $best = ';';
        $bestCount = 0;
        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($header, $delimiter);
            if ($count > $bestCount) {
                [$best, $bestCount] = [$delimiter, $count];
            }
        }

        return $best;
    }

    /** Value of an XLSX cell as typed: a decimal number with a dot, a date as day/month/year. */
    private static function cell(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('d/m/Y'),
            is_bool($value) => $value ? 'oui' : 'non',
            is_float($value) && floor($value) === $value && abs($value) < 1e15 => (string) (int) $value,
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
