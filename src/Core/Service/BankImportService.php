<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use DateTimeImmutable;

final readonly class BankImportService
{
    public function __construct(
        private StorageInterface $storage,
        private PermitService $permitService,
    ) {
    }

    /**
     * Analysiert die hochgeladene CSV, normalisiert Zeilenumbrüche und extrahiert Header & erste Datenzeile.
     *
     * @return array{headers: array<int, string>, previewRow: array<int, string>}
     */
    public function analyzeCsv(string $filePath): array
    {
        if (!\file_exists($filePath)) {
            return ['headers' => [], 'previewRow' => []];
        }

        $this->normalizeLineEndings($filePath);

        $handle = \fopen($filePath, 'r');
        if ($handle === false) {
            return ['headers' => [], 'previewRow' => []];
        }

        $delimiter = $this->detectDelimiter($handle);

        // PHP 8.4+ Fix: Explizite Angabe von Enclosure (") und Escape (\)
        $headers = \fgetcsv($handle, 0, $delimiter, '"', '\\') ?: [];
        $previewRow = \fgetcsv($handle, 0, $delimiter, '"', '\\') ?: [];

        \fclose($handle);

        return [
            'headers' => $this->convertToUtf8($headers),
            'previewRow' => $this->convertToUtf8($previewRow),
        ];
    }

    /**
     * Verarbeitet die Bank-CSV-Datei, addiert Teilzahlungen auf und gleicht sie mit dem System ab.
     *
     * @return array<string, mixed> Resultat der Verarbeitung.
     */
    public function processCsv(string $filePath, int $idCol, int $amountCol, int $dateCol): array
    {
        if (!\file_exists($filePath)) {
            return ['success' => false, 'message' => 'Datei konnte nicht gefunden werden.'];
        }

        $this->normalizeLineEndings($filePath);

        $handle = \fopen($filePath, 'r');
        if ($handle === false) {
            return ['success' => false, 'message' => 'Datei konnte nicht gelesen werden.'];
        }

        $delimiter = $this->detectDelimiter($handle);
        \fgetcsv($handle, 0, $delimiter, '"', '\\'); // Header überspringen

        $aggregierteZahlungen = [];
        $letztesDatumPerPermit = [];
        $fehlerhaft = 0;
        $uebersprungen = 0;
        $erfolgreich = 0;

        while (($row = \fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (\count($row) === 1 && $row[0] === null) {
                continue;
            }

            if (!isset($row[$idCol], $row[$amountCol], $row[$dateCol])) {
                ++$fehlerhaft;
                continue;
            }

            $verwendungszweck = (string) $row[$idCol];
            $betragRaw = (string) $row[$amountCol];
            $datumRaw = (string) $row[$dateCol];

            if (!\preg_match_all('/([ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8})/', \strtoupper($verwendungszweck), $matches)) {
                continue; // Keine eindeutige ID gefunden
            }

            $cleanAmount = \str_replace('.', '', $betragRaw);
            $cleanAmount = \str_replace(',', '.', $cleanAmount);
            $ueberwiesenerBetrag = (float) $cleanAmount;

            foreach ($matches[1] as $permitId) {
                $permitIdStr = $permitId;
                if (!isset($aggregierteZahlungen[$permitIdStr])) {
                    $aggregierteZahlungen[$permitIdStr] = 0.0;
                }
                $aggregierteZahlungen[$permitIdStr] += $ueberwiesenerBetrag;
                $letztesDatumPerPermit[$permitIdStr] = $datumRaw;
            }
        }
        \fclose($handle);

        foreach ($aggregierteZahlungen as $permitId => $gesamtsumme) {
            $permit = $this->storage->findByHash($permitId);

            if (!$permit instanceof Permit) {
                ++$uebersprungen;
                continue;
            }

            if ($permit->isPaid()) {
                ++$uebersprungen;
                continue;
            }

            if (\round($gesamtsumme, 2) >= \round($permit->getPrice(), 2)) {
                $datumRaw = (string) $letztesDatumPerPermit[$permitId];
                $formatierterTag = $this->parseDate($datumRaw);
                $grund = 'Automatisch via Bank-Import freigeschaltet (Summe der Zahlungen: ' . \number_format($gesamtsumme, 2, ',', '.') . ' €)';

                // FIX: Hier auf ->value zugreifen, da PermitCode jetzt ein Objekt ist!
                if ($this->permitService->manualActivate($permit->code->value, $grund, $formatierterTag)) {
                    ++$erfolgreich;
                } else {
                    ++$fehlerhaft;
                }
            } else {
                ++$fehlerhaft;
            }
        }

        @\unlink($filePath);

        return [
            'success' => true,
            'erfolgreich' => $erfolgreich,
            'uebersprungen' => $uebersprungen,
            'fehlerhaft' => $fehlerhaft,
        ];
    }

    /**
     * Normalisiert alte Mac- (\r) und Windows- (\r\n) Zeilenumbrüche zu \n.
     * Das ist die modernste und performanteste Methode für kleine CSV-Dateien und
     * umgeht die veraltete `ini_set('auto_detect_line_endings')` Funktion.
     */
    private function normalizeLineEndings(string $filePath): void
    {
        $content = \file_get_contents($filePath);
        if (!\is_string($content)) {
            return;
        }

        $normalized = \str_replace(["\r\n", "\r"], "\n", $content);
        \file_put_contents($filePath, $normalized);
    }

    /**
     * Erkennt anhand der ersten Zeile dynamisch das Trennzeichen.
     *
     * @param resource $handle
     */
    private function detectDelimiter($handle): string
    {
        $firstLine = \fgets($handle);
        \rewind($handle);

        if ($firstLine !== false && \substr_count($firstLine, ',') > \substr_count($firstLine, ';')) {
            return ',';
        }

        return ';';
    }

    /**
     * Bereinigt und konvertiert ein Array von Strings streng nach UTF-8.
     *
     * @param array<int, mixed> $row
     *
     * @return array<int, string>
     */
    private function convertToUtf8(array $row): array
    {
        $converted = [];
        foreach ($row as $value) {
            if (!\is_string($value) || $value === '') {
                $converted[] = (string) $value;
                continue;
            }
            $encoding = \mb_detect_encoding($value, 'UTF-8, ISO-8859-1, Windows-1252', true);
            $converted[] = $encoding ? \mb_convert_encoding($value, 'UTF-8', $encoding) : \mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return $converted;
    }

    /**
     * Parst ein Bank-Datum flexibel.
     */
    private function parseDate(string $rawDate): string
    {
        $trimmed = \trim($rawDate);
        $dateObj = DateTimeImmutable::createFromFormat('d.m.y', $trimmed);

        if ($dateObj === false) {
            $dateObj = DateTimeImmutable::createFromFormat('d.m.Y', $trimmed);
        }

        return $dateObj !== false ? $dateObj->format('d.m.Y') : $trimmed;
    }
}
