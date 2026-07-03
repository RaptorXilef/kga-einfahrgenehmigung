<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;

final readonly class BankImportService
{
    public function __construct(
        private StorageInterface $storage,
        private PermitService $permitService,
    ) {
    }

    /**
     * Analysiert die erste Zeile der CSV, um Spalten-Header zurückzugeben.
     */
    public function extractHeaders(string $filePath): array
    {
        if (! \file_exists($filePath) || ! $handle = \fopen($filePath, 'r')) {
            return [];
        }

        // Dynamische Erkennung des Trennzeichens (Komma oder Semikolon)
        $firstLine = \fgets($handle);
        \rewind($handle);

        $delimiter = ';';
        if ($firstLine !== false && \substr_count($firstLine, ',') > \substr_count($firstLine, ';')) {
            $delimiter = ',';
        }

        // PHP 8.4+ Fix: Explizite Angabe von Enclosure (") und Escape (\)
        $headers = \fgetcsv($handle, 0, $delimiter, '"', '\\');
        \fclose($handle);

        return $headers !== false ? $headers : [];
    }

    /**
     * Verarbeitet die CSV-Datei, addiert Teilzahlungen auf und löscht die CSV anschließend.
     */
    public function processCsv(
        string $filePath,
        int $idCol,
        int $amountCol,
        int $dateCol,
    ): array {
        if (! \file_exists($filePath) || ! $handle = \fopen($filePath, 'r')) {
            return ['success' => false, 'message' => 'Datei konnte nicht geöffnet werden.'];
        }

        $firstLine = \fgets($handle);
        \rewind($handle);

        $delimiter = ';';
        if ($firstLine !== false && \substr_count($firstLine, ',') > \substr_count($firstLine, ';')) {
            $delimiter = ',';
        }

        // Header überspringen (PHP 8.4+ Fix)
        \fgetcsv($handle, 0, $delimiter, '"', '\\');

        $aggregierteZahlungen  = [];
        $letztesDatumPerPermit = [];
        $fehlerhaft            = 0;
        $uebersprungen         = 0;
        $erfolgreich           = 0;

        // Schleife 1: Sammeln und Addieren (Teilzahlungs-Sonderfall abdecken)
        // PHP 8.4+ Fix: Explizite Angabe von Enclosure (") und Escape (\)
        while (($row = \fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // Leere Zeilen abfangen
            if (empty($row) || (\count($row) === 1 && $row[0] === null)) {
                continue;
            }

            if (! isset($row[$idCol], $row[$amountCol], $row[$dateCol])) {
                ++$fehlerhaft;

                continue;
            }

            $verwendungszweck = (string) $row[$idCol];
            $betragRaw        = (string) $row[$amountCol];
            $datumRaw         = (string) $row[$dateCol];

            if (! \preg_match('/([ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8})/', \strtoupper($verwendungszweck), $matches)) {
                continue; // Keine eindeutige ID gefunden, Zeile ist nicht relevant
            }

            $permitId = $matches[1];

            // Deutsches Zahlenformat (z.B. 1.250,50) zu Float (1250.50) umwandeln
            $cleanAmount         = \str_replace('.', '', $betragRaw);
            $cleanAmount         = \str_replace(',', '.', $cleanAmount);
            $ueberwiesenerBetrag = (float) $cleanAmount;

            if (! isset($aggregierteZahlungen[$permitId])) {
                $aggregierteZahlungen[$permitId] = 0.0;
            }
            // Beträge addieren
            $aggregierteZahlungen[$permitId] += $ueberwiesenerBetrag;
            $letztesDatumPerPermit[$permitId] = $datumRaw;
        }

        \fclose($handle);

        // Schleife 2: System-Abgleich der summierten Beträge
        foreach ($aggregierteZahlungen as $permitId => $gesamtsumme) {
            // FIX: $permitId strikt als String übergeben!
            $permit = $this->storage->findByHash((string) $permitId);

            if (! $permit instanceof Permit) {
                ++$uebersprungen;

                continue;
            }

            if ($permit->isPaid()) {
                ++$uebersprungen;

                continue;
            }

            $sollBetrag = $permit->getPrice();

            // Auf Cent genau runden, um Float-Rundungsfehler zu vermeiden
            if (\round($gesamtsumme, 2) >= \round($sollBetrag, 2)) {
                $datumRaw = $letztesDatumPerPermit[$permitId];

                // Datum flexibel parsen (dd.mm.yy oder dd.mm.yyyy)
                $dateObj = \DateTimeImmutable::createFromFormat('d.m.y', \trim($datumRaw));

                if ($dateObj === false) {
                    $dateObj = \DateTimeImmutable::createFromFormat('d.m.Y', \trim($datumRaw));
                }

                $formatierterTag = $dateObj !== false ? $dateObj->format('d.m.Y') : \trim($datumRaw);

                $grund = 'Automatisch via Bank-Import freigeschaltet (Summe der Zahlungen: ' . \number_format($gesamtsumme, 2, ',', '.') . ' €)';

                if ($this->permitService->manualActivate($permit->code, $grund, $formatierterTag)) {
                    ++$erfolgreich;
                } else {
                    ++$fehlerhaft;
                }
            } else {
                // Gesamtsumme reicht nicht aus (z.B. weil nur ein Teilbetrag gezahlt wurde)
                ++$fehlerhaft;
            }
        }

        // DATENSCHUTZ: Datei nach der Verarbeitung physisch vom Server löschen!
        if (\file_exists($filePath)) {
            @\unlink($filePath);
        }

        return [
            'success'       => true,
            'erfolgreich'   => $erfolgreich,
            'uebersprungen' => $uebersprungen,
            'fehlerhaft'    => $fehlerhaft,
        ];
    }
}
