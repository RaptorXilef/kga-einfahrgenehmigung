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
     * @return array<string, mixed> Resultat der Verarbeitung inklusive detaillierter Begründungen.
     */
    public function processCsv(string $filePath, int $idCol, int $amountCol, int $dateCol): array
    {
        if (!\file_exists($filePath)) {
            \error_log("BankImport Fehler: Die Datei '{$filePath}' konnte nicht gefunden werden.");

            return ['success' => false, 'message' => 'Datei konnte nicht gefunden werden.'];
        }

        \error_log('BankImport: Starte Verarbeitung der CSV-Datei...');

        $this->normalizeLineEndings($filePath);

        $handle = \fopen($filePath, 'r');
        if ($handle === false) {
            \error_log("BankImport Fehler: Die Datei '{$filePath}' konnte nicht zum Lesen geöffnet werden.");

            return ['success' => false, 'message' => 'Datei konnte nicht gelesen werden.'];
        }

        $delimiter = $this->detectDelimiter($handle);
        \fgetcsv($handle, 0, $delimiter, '"', '\\'); // Header überspringen

        $aggregierteZahlungen = [];
        $letztesDatumPerPermit = [];

        // Wir sammeln jetzt detailliert die Codes anstatt nur hochzuzählen
        $erfolgreichDetails = [];
        $uebersprungenDetails = [];
        $fehlerhaftDetails = [];
        $unlesbareZeilenDetails = [];

        $rowNumber = 1;

        while (($row = \fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            ++$rowNumber;

            if (\count($row) === 1 && $row[0] === null) {
                continue;
            }

            if (!isset($row[$idCol], $row[$amountCol], $row[$dateCol])) {
                $colCount = \count($row);
                $errorMsg = "Zeile {$rowNumber} (Spalten fehlen, nur {$colCount} vorhanden)";
                \error_log("BankImport [Zeile {$rowNumber}] Fehler: Benötigte Spalten fehlen. Verfügbare Spalten: {$colCount}.");
                $unlesbareZeilenDetails[] = $errorMsg;
                continue;
            }

            $verwendungszweck = (string) $row[$idCol];
            $betragRaw = (string) $row[$amountCol];
            $datumRaw = (string) $row[$dateCol];

            if (!\preg_match_all('/([ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8})/', \strtoupper($verwendungszweck), $matches)) {
                \error_log("BankImport [Zeile {$rowNumber}] Info: Kein 8-stelliger System-Code gefunden. Rohdaten Zweck: '{$verwendungszweck}'");
                continue;
            }

            $cleanAmount = \str_replace('.', '', $betragRaw);
            $cleanAmount = \str_replace(',', '.', $cleanAmount);
            $ueberwiesenerBetrag = (float) $cleanAmount;

            $gefundeneCodes = \implode(', ', $matches[1]);
            \error_log("BankImport [Zeile {$rowNumber}] Info: Code(s) erkannt: [{$gefundeneCodes}]. Lese Betrag: {$ueberwiesenerBetrag} €");

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

        \error_log('BankImport: Dateidurchlauf beendet. Starte Datenbank-Abgleich...');

        foreach ($aggregierteZahlungen as $permitId => $gesamtsumme) {
            $permit = $this->storage->findByHash($permitId);

            if (!$permit instanceof Permit) {
                \error_log("BankImport [Code {$permitId}] Übersprungen: Code existiert nicht in der Datenbank.");
                $uebersprungenDetails[] = "{$permitId} (Nicht in Datenbank)";
                continue;
            }

            $ownerName = $permit->getOwnerName();

            if ($permit->isPaid()) {
                \error_log("BankImport [Code {$permitId}] Übersprungen: Genehmigung für '{$ownerName}' ist im System bereits als BEZAHLT markiert.");
                $uebersprungenDetails[] = "{$permitId} (Bereits bezahlt - {$ownerName})";
                continue;
            }

            $sollBetrag = \round($permit->getPrice(), 2);
            $istBetrag = \round($gesamtsumme, 2);

            $sollFormatted = \number_format($sollBetrag, 2, ',', '.') . ' €';
            $istFormatted = \number_format($istBetrag, 2, ',', '.') . ' €';

            if ($istBetrag >= $sollBetrag) {
                $datumRaw = (string) $letztesDatumPerPermit[$permitId];
                $formatierterTag = $this->parseDate($datumRaw);
                $grund = 'Automatisch via Bank-Import freigeschaltet (Summe der Zahlungen: ' . $istFormatted . ')';

                if ($this->permitService->manualActivate($permit->code->value, $grund, $formatierterTag)) {
                    \error_log("BankImport [Code {$permitId}] ERFOLG: Zahlung von {$istBetrag} € für '{$ownerName}' (Soll: {$sollBetrag} €) verbucht.");
                    $erfolgreichDetails[] = "{$permitId} ({$istFormatted} - {$ownerName})";
                } else {
                    \error_log("BankImport [Code {$permitId}] KRITISCHER FEHLER: Konnte Status für '{$ownerName}' nicht auf Bezahlt setzen.");
                    $fehlerhaftDetails[] = "{$permitId} (Speicherfehler - {$ownerName})";
                }
            } else {
                \error_log("BankImport [Code {$permitId}] FEHLER: Betrag reicht für '{$ownerName}' nicht aus. (Soll: {$sollBetrag} €, Ist: {$istBetrag} €)");
                $fehlerhaftDetails[] = "{$permitId} ({$istFormatted} statt {$sollFormatted} - {$ownerName})";
            }
        }

        // Duplikate entfernen, falls ein Code mehrfach aufgeschlagen ist
        $erfolgreichDetails = \array_values(\array_unique($erfolgreichDetails));
        $uebersprungenDetails = \array_values(\array_unique($uebersprungenDetails));
        $fehlerhaftDetails = \array_values(\array_unique($fehlerhaftDetails));
        $unlesbareZeilenDetails = \array_values(\array_unique($unlesbareZeilenDetails));

        $erfCount = \count($erfolgreichDetails);
        $uebCount = \count($uebersprungenDetails);
        $fehlCount = \count($fehlerhaftDetails) + \count($unlesbareZeilenDetails);

        \error_log("BankImport: Abgleich komplett. Resultat -> Erfolgreich: {$erfCount} | Übersprungen: {$uebCount} | Fehlerhaft: {$fehlCount}");

        @\unlink($filePath);

        return [
            'success' => true,
            'erfolgreich_count' => $erfCount,
            'uebersprungen_count' => $uebCount,
            'fehlerhaft_count' => $fehlCount,
            'erfolgreich_details' => $erfolgreichDetails,
            'uebersprungen_details' => $uebersprungenDetails,
            'fehlerhaft_details' => $fehlerhaftDetails,
            'unlesbare_zeilen_details' => $unlesbareZeilenDetails,
        ];
    }

    /**
     * Normalisiert alte Mac- (\r) und Windows- (\r\n) Zeilenumbrüche zu \n.
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
