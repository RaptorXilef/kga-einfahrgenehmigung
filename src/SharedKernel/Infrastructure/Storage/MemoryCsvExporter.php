<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Storage;

use App\Contracts\System\CsvExporterInterface;
use Override;

/**
 * Physische Stream-Implementierung für den CSV-Export über php://temp.
 * Schützt automatisch vor CSV-Formula-Injection (Excel/Calc).
 */
final readonly class MemoryCsvExporter implements CsvExporterInterface
{
    #[Override]
    public function export(array $headers, iterable $rows, string $delimiter = ';'): string
    {
        $output = \fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        // UTF-8 BOM für korrekte Umlaut-Darstellung in Microsoft Excel
        \fwrite($output, "\xEF\xBB\xBF");
        \fputcsv($output, $headers, $delimiter, '"', '\\');

        foreach ($rows as $row) {
            $sanitizedRow = [];
            foreach ($row as $cell) {
                $sanitizedRow[] = $this->sanitizeCsvCell($cell);
            }
            \fputcsv($output, $sanitizedRow, $delimiter, '"', '\\');
        }

        \rewind($output);
        $content = \stream_get_contents($output);
        \fclose($output);

        return \is_string($content) ? $content : '';
    }

    /**
     * Verhindert CSV-Injection (Formel-Ausführung in Tabellenkalkulationen).
     */
    private function sanitizeCsvCell(mixed $value): string|int|float
    {
        if (\is_int($value) || \is_float($value)) {
            return $value;
        }

        $str = (string) $value;
        if ($str === '') {
            return '';
        }

        if (\in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }

        return $str;
    }
}
