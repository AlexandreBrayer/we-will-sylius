<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Infrastructure\Import;

use App\Yack\Catalog\Domain\Import\ImportColumn;
use App\Yack\Catalog\Domain\Import\ImportFormat;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Templates of the manual import, written from the column contract (ImportColumn): the headers and
 * one example line. CSV in UTF-8 with BOM and semicolons, as a French spreadsheet opens it; XLSX
 * written with OpenSpout (ADR 0010).
 */
final readonly class ImportTemplates
{
    public const FILE_NAME = 'modele-import-catalogue';

    /** Content of the template in this format. */
    public function content(ImportFormat $format): string
    {
        return ImportFormat::Csv === $format ? $this->csv() : $this->xlsx();
    }

    public static function fileName(ImportFormat $format): string
    {
        return self::FILE_NAME . '.' . $format->value;
    }

    private function csv(): string
    {
        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new \RuntimeException('Temporary stream cannot be opened.');
        }
        foreach (self::lines() as $line) {
            fputcsv($stream, $line, ';', '"', '');
        }
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF" . $content;
    }

    private function xlsx(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'yack-template');

        try {
            $writer = new Writer();
            $writer->openToFile($path);
            [$headers, $example] = self::lines();
            $writer->addRow(Row::fromValues($headers, (new Style())->setFontBold()));
            $writer->addRow(Row::fromValues($example));
            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            unlink($path);
        }
    }

    /** @return array{list<string>, list<string>} */
    private static function lines(): array
    {
        return [
            array_map(static fn (ImportColumn $column): string => $column->header(), ImportColumn::cases()),
            array_map(static fn (ImportColumn $column): string => $column->example(), ImportColumn::cases()),
        ];
    }
}
