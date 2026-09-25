<?php

declare(strict_types=1);

namespace App\Contracts\System;

/**
 * Port für das speicherschonende Erzeugen von UTF-8 CSV-Dateien (inkl. BOM und Formula-Injection-Schutz).
 * Entkoppelt die Application-Schicht von nativen Stream-/Dateisystem-Funktionen (fopen, fputcsv).
 */
interface CsvExporterInterface
{
    /**
     * Schreibt Kopfzeile und Daten-Stream (z.B. aus einem Generator) in einen temporären Memory-Stream
     * und gibt den fertigen CSV-String zurück.
     *
     * @param array<int, string> $headers
     * @param iterable<int, array<int, scalar|null>> $rows
     */
    public function export(array $headers, iterable $rows, string $delimiter = ';'): string;
}
