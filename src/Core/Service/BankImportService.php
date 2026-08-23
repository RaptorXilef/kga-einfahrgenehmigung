<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use DateTimeImmutable;

final readonly class BankImportService
{
    public function __construct(
        private StorageInterface $storage,
        private PermitService $permitService,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Analysiert die hochgeladene CSV, wäscht sie komplett rein und extrahiert Header & erste Datenzeile.
     *
     * @return array{headers: array<int, string>, previewRow: array<int, string>}
     */
    public function analyzeCsv(string $filePath): array
    {
        if (!\file_exists($filePath)) {
            return ['headers' => [], 'previewRow' => []];
        }

        // 1. Die komplette Datei vorab waschen (BOM, Encoding, Umbrüche)
        $this->prepareAndNormalizeFile($filePath);

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
            'headers' => $headers,
            'previewRow' => $previewRow,
        ];
    }

    /**
     * Verarbeitet die gereinigte Bank-CSV-Datei, addiert Teilzahlungen auf und gleicht sie mit dem System ab.
     *
     * @return array<string, mixed> Resultat der Verarbeitung inklusive detaillierter Begründungen.
     */
    public function processCsv(string $filePath, int $idCol, int $amountCol, int $dateCol): array
    {
        if (!\file_exists($filePath)) {
            $this->writeLog("Fehler: Die Datei '{$filePath}' konnte nicht gefunden werden.");

            return ['success' => false, 'message' => 'Datei konnte nicht gefunden werden.'];
        }

        $this->writeLog('Starte Dateireinigung und Verarbeitung der CSV...');

        // 1. Die komplette Datei vorab waschen (BOM, Encoding, Umbrüche)
        $this->prepareAndNormalizeFile($filePath);

        $handle = \fopen($filePath, 'r');
        if ($handle === false) {
            $this->writeLog("Fehler: Die Datei '{$filePath}' konnte nicht zum Lesen geöffnet werden.");

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
                $this->writeLog("[Zeile {$rowNumber}] Fehler: Benötigte Spalten fehlen. Verfügbare Spalten: {$colCount}.");
                $unlesbareZeilenDetails[] = $errorMsg;
                continue;
            }

            $verwendungszweck = (string) $row[$idCol];
            $betragRaw = (string) $row[$amountCol];
            $datumRaw = (string) $row[$dateCol];

            if (!\preg_match_all('/([ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8})/', \strtoupper($verwendungszweck), $matches)) {
                $this->writeLog("[Zeile {$rowNumber}] Info: Kein 8-stelliger System-Code gefunden. Rohdaten Zweck: '{$verwendungszweck}'");
                continue;
            }

            $cleanAmount = \str_replace('.', '', $betragRaw);
            $cleanAmount = \str_replace(',', '.', $cleanAmount);
            $ueberwiesenerBetrag = (float) $cleanAmount;

            $gefundeneCodes = \implode(', ', $matches[1]);
            $this->writeLog("[Zeile {$rowNumber}] Info: Code(s) erkannt: [{$gefundeneCodes}]. Lese Betrag: {$ueberwiesenerBetrag} €");

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

        $this->writeLog('Dateidurchlauf beendet. Starte Datenbank-Abgleich...');

        foreach ($aggregierteZahlungen as $permitId => $gesamtsumme) {
            $permit = $this->storage->findByHash($permitId);

            if (!$permit instanceof Permit) {
                $this->writeLog("[Code {$permitId}] Übersprungen: Code existiert nicht in der Datenbank.");
                $uebersprungenDetails[] = "{$permitId} (Nicht in Datenbank)";
                continue;
            }

            $ownerName = $permit->getOwnerName();

            if ($permit->isPaid()) {
                $this->writeLog("[Code {$permitId}] Übersprungen: Genehmigung für '{$ownerName}' ist im System bereits als BEZAHLT markiert.");
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

                // Permit erwartet `$permit->code` als String (ValueObject wurde hier schon dekonstruiert oder ist String, wir sichern uns mit ->value ab falls nötig, im Rector-Code war es String)
                $codeToActivate = \is_string($permit->code) ? $permit->code : $permit->code->value;

                if ($this->permitService->manualActivate($codeToActivate, $grund, $formatierterTag)) {
                    $this->writeLog("[Code {$permitId}] ERFOLG: Zahlung von {$istBetrag} € für '{$ownerName}' (Soll: {$sollBetrag} €) verbucht.");
                    $erfolgreichDetails[] = "{$permitId} ({$istFormatted} - {$ownerName})";
                } else {
                    $this->writeLog("[Code {$permitId}] KRITISCHER FEHLER: Konnte Status für '{$ownerName}' nicht auf Bezahlt setzen.");
                    $fehlerhaftDetails[] = "{$permitId} (Speicherfehler - {$ownerName})";
                }
            } else {
                $this->writeLog("[Code {$permitId}] FEHLER: Betrag reicht für '{$ownerName}' nicht aus. (Soll: {$sollBetrag} €, Ist: {$istBetrag} €)");
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

        $this->writeLog("Abgleich komplett. Resultat -> Erfolgreich: {$erfCount} | Übersprungen: {$uebCount} | Fehlerhaft: {$fehlCount}\n---");

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
     * Schreibt eine formatierte Log-Nachricht in die dedizierte Bank-Import-Logdatei.
     */
    private function writeLog(string $message): void
    {
        $logDir = $this->config->getStoragePath('logs');
        if (!\is_dir($logDir)) {
            @\mkdir($logDir, 0o755, true);
        }

        $logFile = $logDir . '/bank_import.log';
        $timestamp = \date('d-M-Y H:i:s e');
        $formattedMessage = "[$timestamp] BankImport: $message\n";

        @\file_put_contents($logFile, $formattedMessage, \FILE_APPEND | \LOCK_EX);
    }

    /**
     * DIE WASCHANLAGE FÜR CSV-DATEIEN.
     * Bereinigt die CSV-Datei komplett im RAM, bevor PHP sie iteriert.
     * 1. Entfernt unsichtbare UTF-8 BOMs
     * 2. Erkennt das globale File-Encoding zuverlässig und konvertiert zu UTF-8
     * 3. Normalisiert Mac/Windows Line-Endings zu sauberen \n Umbrüchen
     */
    private function prepareAndNormalizeFile(string $filePath): void
    {
        $content = \file_get_contents($filePath);
        if (!\is_string($content) || $content === '') {
            return;
        }

        // 1. UTF-8 BOM entfernen (Hex: EF BB BF)
        if (\str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = \substr($content, 3);
        }

        // 2. Globale Encoding-Erkennung
        // Viel robuster als zellbasierte Erkennung, da der Textkorpus groß genug für korrekte Analyse ist.
        $encoding = \mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-15', 'ISO-8859-1', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = \mb_convert_encoding($content, 'UTF-8', $encoding);
        } elseif (!$encoding) {
            // Fallback auf klassisches Banken-ANSI, falls die Erkennung fehlschlägt
            $content = \mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        // 3. Line Endings normalisieren (Mac \r oder Windows \r\n zu Unix \n)
        $content = \str_replace(["\r\n", "\r"], "\n", $content);

        // 4. Gewaschenen Text speichern
        \file_put_contents($filePath, $content);
    }

    /**
     * Erkennt anhand der ersten Zeile dynamisch das Trennzeichen.
     * Analysiert ; , \t und | nach Häufigkeit.
     *
     * @param resource $handle
     */
    private function detectDelimiter($handle): string
    {
        $firstLine = \fgets($handle);
        \rewind($handle);

        if ($firstLine === false) {
            return ';';
        }

        $delimiters = [
            ';' => \substr_count($firstLine, ';'),
            ',' => \substr_count($firstLine, ','),
            "\t" => \substr_count($firstLine, "\t"),
            '|' => \substr_count($firstLine, '|'),
        ];

        // Absteigend sortieren, den Key mit dem höchsten Wert zurückgeben
        \arsort($delimiters);

        return (string) \array_key_first($delimiters);
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
