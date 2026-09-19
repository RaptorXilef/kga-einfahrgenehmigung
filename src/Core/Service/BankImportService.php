<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use DateTimeImmutable;
use Exception;
use League\Csv\Reader;
use ZipArchive;

final readonly class BankImportService
{
    public function __construct(
        private StorageInterface $storage,
        private PermitService $permitService,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Analysiert die hochgeladene CSV, wäscht sie komplett rein und extrahiert Header & erste Datenzeile via League\Csv.
     *
     * @return array{headers: array<int, string>, previewRow: array<int, string>}
     */
    public function analyzeCsv(string $filePath): array
    {
        if (!\file_exists($filePath)) {
            return ['headers' => [], 'previewRow' => []];
        }

        $this->prepareAndNormalizeFile($filePath);

        try {
            // FIX: Umgehung der createFromPath Deprecation (P1007) durch Verwendung eines direkten Streams
            $stream = \fopen($filePath, 'r');
            if ($stream === false) {
                return ['headers' => [], 'previewRow' => []];
            }

            $csv = Reader::createFromStream($stream);
            $csv->setDelimiter($this->detectDelimiter($filePath));
            $csv->setHeaderOffset(null); // Wir arbeiten mit numerischen Indizes

            $iterator = $csv->getIterator();
            $iterator->rewind();

            // Zeile 0: Header
            $headers = $iterator->valid() ? $iterator->current() : [];

            // Zeile 1: Erste Datenzeile
            $iterator->next();
            $previewRow = $iterator->valid() ? $iterator->current() : [];

            return [
                'headers' => \is_array($headers) ? $headers : [],
                'previewRow' => \is_array($previewRow) ? $previewRow : [],
            ];
        } catch (Exception $e) {
            \error_log('BankImportService (analyzeCsv) Error: ' . $e->getMessage());

            return ['headers' => [], 'previewRow' => []];
        }
    }

    /**
     * Verarbeitet die gereinigte Bank-CSV-Datei, addiert Teilzahlungen auf und gleicht sie mit dem System ab.
     *
     * @return array<string, mixed> Resultat der Verarbeitung inklusive detaillierter Begründungen.
     */
    public function processCsv(string $filePath, int $idCol, int $amountCol, int $dateCol): array
    {
        $runLogs = [];

        if (!\file_exists($filePath)) {
            $this->writeLog("Fehler: Die Datei '{$filePath}' konnte nicht gefunden werden.", $runLogs);

            return ['success' => false, 'message' => 'Datei konnte nicht gefunden werden.'];
        }

        $this->writeLog('Starte Dateireinigung und Verarbeitung der CSV via League/Csv...', $runLogs);

        $this->prepareAndNormalizeFile($filePath);

        try {
            // FIX: Umgehung der createFromPath Deprecation (P1007)
            $stream = \fopen($filePath, 'r');
            if ($stream === false) {
                return ['success' => false, 'message' => 'Datei konnte nicht zum Lesen geöffnet werden.'];
            }

            $csv = Reader::createFromStream($stream);
            $csv->setDelimiter($this->detectDelimiter($filePath));
            // Wir überspringen die Kopfzeile
            $csv->setHeaderOffset(null);
        } catch (Exception $e) {
            $this->writeLog('Fehler beim Initialisieren des CSV Readers: ' . $e->getMessage(), $runLogs);

            return ['success' => false, 'message' => 'CSV Format ist ungültig oder beschädigt.'];
        }

        $aggregierteZahlungen = [];
        $letztesDatumPerPermit = [];
        $methodenPerPermit = [];

        $erfolgreichDetails = [];
        $skippedNotInCsv = [];
        $skippedAlreadyPaid = [];
        $skippedNotInDb = [];
        $fehlerhaftPartial = [];
        $fehlerhaftStorage = [];
        $unlesbareZeilenDetails = [];
        $sammelTransfers = [];

        $unpaidCodes = [];
        $unpaidPlates = [];
        $allCodes = [];

        foreach ($this->storage->getAll() as $permit) {
            $c = $permit->code->value;
            $allCodes[$c] = true;
            if (!$permit->isPaid()) {
                $unpaidCodes[$c] = $permit->getOwnerName();
                $unpaidPlates[$c] = $permit->getLicensePlate();
            }
        }

        $missingUnpaidCodes = $unpaidCodes;
        $rowNumber = 0;

        foreach ($csv->getRecords() as $index => $row) {
            ++$rowNumber;

            // Header (Zeile 0) überspringen
            if ($index === 0) {
                continue;
            }

            if (\count($row) === 1 && ($row[0] === null || \trim((string) $row[0]) === '')) {
                continue;
            }

            if (!isset($row[$idCol], $row[$amountCol], $row[$dateCol])) {
                $colCount = \count($row);
                $errorMsg = "Zeile {$rowNumber} (Spalten fehlen, nur {$colCount} vorhanden)";
                $this->writeLog("[Zeile {$rowNumber}] Fehler: Benötigte Spalten fehlen. Verfügbare Spalten: {$colCount}.", $runLogs);
                $unlesbareZeilenDetails[] = $errorMsg;
                continue;
            }

            $verwendungszweck = (string) $row[$idCol];
            $betragRaw = (string) $row[$amountCol];
            $datumRaw = (string) $row[$dateCol];

            $zweckUpper = \strtoupper($verwendungszweck);

            $gefundeneCodes = [];
            $matchMethodsForLine = [];
            $matchMethodsMap = [];

            // 1. Hauptverarbeitung: Suche aktiv nach unbezahlten IDs
            foreach ($unpaidCodes as $unpaidCode => $ownerName) {
                if (\str_contains($zweckUpper, $unpaidCode)) {
                    $gefundeneCodes[] = $unpaidCode;
                    $matchMethodsForLine[] = 'Direktsuche (Unbezahlt)';
                    $matchMethodsMap[$unpaidCode] = 'Direktsuche';
                }
            }

            // 2. Fallback: Regex, um alte/bezahlte Codes oder reine Tippfehler zu finden
            if (empty($gefundeneCodes)) {
                if (\preg_match_all('/([ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8})/', $zweckUpper, $matches)) {
                    foreach ($matches[1] as $m) {
                        if (isset($allCodes[$m])) {
                            $gefundeneCodes[] = $m;
                            $matchMethodsForLine[] = 'Regex Fallback (Bereits im System)';
                            $matchMethodsMap[$m] = 'Regex: Bezahlt';
                        } elseif (\preg_match('/\b' . $m . '\b/', $zweckUpper)) {
                            $gefundeneCodes[] = $m;
                            $matchMethodsForLine[] = 'Regex Fallback (Unbekannter Code, isoliertes Wort)';
                            $matchMethodsMap[$m] = 'Regex: Unbekannt';
                        }
                    }
                }
            }

            $cleanAmount = \str_replace('.', '', $betragRaw);
            $cleanAmount = \str_replace(',', '.', $cleanAmount);
            $ueberwiesenerBetrag = (float) $cleanAmount;

            $anomalyId = 'sam_' . \md5($datumRaw . $betragRaw . $verwendungszweck);

            // 3. Fallback: Kennzeichen-Suche (Wenn ID komplett vergessen wurde)
            $gefundeneKennzeichen = [];
            if (empty($gefundeneCodes)) {
                $zweckNormalized = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', $zweckUpper);
                foreach ($unpaidPlates as $unpaidCode => $plate) {
                    if (empty($plate)) {
                        continue;
                    }

                    $plateNormalized = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', \strtoupper($plate));
                    if (\strlen($plateNormalized) >= 4 && \str_contains($zweckNormalized, $plateNormalized)) {
                        $gefundeneKennzeichen[$unpaidCode] = $plate;
                    }
                }
            }

            if (empty($gefundeneCodes) && empty($gefundeneKennzeichen)) {
                $this->writeLog("[Zeile {$rowNumber}] Info: Kein System-Code und kein Kennzeichen gefunden. Rohdaten Zweck: '{$verwendungszweck}'", $runLogs);
                continue;
            }

            if (empty($gefundeneCodes) && !empty($gefundeneKennzeichen)) {
                $matchedCodes = \array_keys($gefundeneKennzeichen);
                $codesStr = \implode(', ', $matchedCodes);
                $platesStr = \implode(', ', \array_values($gefundeneKennzeichen));

                $this->writeLog("[Zeile {$rowNumber}] HINWEIS: Kein Code, aber Kennzeichen [{$platesStr}] für Codes [{$codesStr}] gefunden. Ausgesteuert zur manuellen Prüfung.", $runLogs);

                $sammelTransfers[] = [
                    'id' => $anomalyId,
                    'date' => $this->parseDate($datumRaw),
                    'amount' => $ueberwiesenerBetrag,
                    'purpose' => $verwendungszweck,
                    'codes' => $matchedCodes,
                    'type' => 'kennzeichen',
                ];

                foreach ($matchedCodes as $c) {
                    unset($missingUnpaidCodes[$c]);
                }
                continue;
            }

            $gefundeneCodes = \array_values(\array_unique($gefundeneCodes));
            $matchMethodsForLine = \array_values(\array_unique($matchMethodsForLine));

            if (\count($gefundeneCodes) > 1) {
                $codesStr = \implode(', ', $gefundeneCodes);
                $this->writeLog("[Zeile {$rowNumber}] FEHLER: Mehrere Codes in einer Überweisung gefunden [{$codesStr}]. Wird zur manuellen Prüfung ausgesteuert.", $runLogs);

                $sammelTransfers[] = [
                    'id' => $anomalyId,
                    'date' => $this->parseDate($datumRaw),
                    'amount' => $ueberwiesenerBetrag,
                    'purpose' => $verwendungszweck,
                    'codes' => $gefundeneCodes,
                    'type' => 'sammel',
                ];

                foreach ($gefundeneCodes as $c) {
                    unset($missingUnpaidCodes[$c]);
                }
                continue;
            }

            $codesStr = \implode(', ', $gefundeneCodes);
            $methodStr = \implode(' & ', $matchMethodsForLine);

            $this->writeLog("[Zeile {$rowNumber}] Info: Code(s) erkannt: [{$codesStr}] via {$methodStr}. Lese Betrag: {$ueberwiesenerBetrag} €", $runLogs);

            foreach ($gefundeneCodes as $permitIdStr) {
                $aggregierteZahlungen[$permitIdStr] ??= 0.0;
                $aggregierteZahlungen[$permitIdStr] += $ueberwiesenerBetrag;
                $letztesDatumPerPermit[$permitIdStr] = $datumRaw;
                $methodenPerPermit[$permitIdStr] = $matchMethodsMap[$permitIdStr] ?? 'Unbekannt';
                unset($missingUnpaidCodes[$permitIdStr]);
            }
        }

        foreach ($missingUnpaidCodes as $missingCode => $ownerName) {
            $this->writeLog("[Code {$missingCode}] Fehlt in CSV: Unbezahlte Genehmigung für '{$ownerName}' wurde nicht gefunden.", $runLogs);
            $skippedNotInCsv[] = "{$missingCode} ({$ownerName})";
        }

        $this->writeLog('Dateidurchlauf beendet. Starte Datenbank-Abgleich...', $runLogs);

        foreach ($aggregierteZahlungen as $permitId => $gesamtsumme) {
            $method = $methodenPerPermit[$permitId] ?? 'Unbekannt';
            $permit = $this->storage->findByHash($permitId);

            if (!$permit instanceof Permit) {
                $this->writeLog("[Code {$permitId}] Übersprungen: Code existiert nicht in der Datenbank (Erkannt via: {$method}).", $runLogs);
                $skippedNotInDb[] = $permitId;
                continue;
            }

            $ownerName = $permit->getOwnerName();

            if ($permit->isPaid()) {
                $this->writeLog("[Code {$permitId}] Übersprungen: Genehmigung für '{$ownerName}' ist im System bereits als BEZAHLT markiert (Erkannt via: {$method}).", $runLogs);
                $skippedAlreadyPaid[] = "{$permitId} ({$ownerName})";
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

                $codeToActivate = \is_string($permit->code) ? $permit->code : $permit->code->value;

                if ($this->permitService->manualActivate($codeToActivate, $grund, $formatierterTag)) {
                    $this->writeLog("[Code {$permitId}] ERFOLG: Zahlung von {$istBetrag} € für '{$ownerName}' (Soll: {$sollBetrag} €) verbucht (Erkannt via: {$method}).", $runLogs);
                    $erfolgreichDetails[] = "{$permitId} ({$ownerName})";
                } else {
                    $this->writeLog("[Code {$permitId}] KRITISCHER FEHLER: Konnte Status für '{$ownerName}' nicht auf Bezahlt setzen (Erkannt via: {$method}).", $runLogs);
                    $fehlerhaftStorage[] = "{$permitId} ({$ownerName})";
                }
            } else {
                $this->writeLog("[Code {$permitId}] FEHLER: Betrag reicht für '{$ownerName}' nicht aus. (Soll: {$sollBetrag} €, Ist: {$istBetrag} €) (Erkannt via: {$method}).", $runLogs);
                $fehlerhaftPartial[] = "{$permitId} ({$ownerName}: {$istFormatted} statt {$sollFormatted})";
            }
        }

        // Bündelung für das Frontend (Lesbare Listen statt Chaos)
        $erfolgreichDetails = \array_values(\array_unique($erfolgreichDetails));
        $skippedNotInCsv = \array_values(\array_unique($skippedNotInCsv));
        $skippedAlreadyPaid = \array_values(\array_unique($skippedAlreadyPaid));
        $skippedNotInDb = \array_values(\array_unique($skippedNotInDb));
        $fehlerhaftPartial = \array_values(\array_unique($fehlerhaftPartial));
        $fehlerhaftStorage = \array_values(\array_unique($fehlerhaftStorage));
        $unlesbareZeilenDetails = \array_values(\array_unique($unlesbareZeilenDetails));

        $uebersprungenDetails = [];
        if (!empty($skippedNotInCsv)) {
            $uebersprungenDetails['Fehlt auf Auszug (in CSV)'] = $skippedNotInCsv;
        }
        if (!empty($skippedAlreadyPaid)) {
            $uebersprungenDetails['Bereits verbucht'] = $skippedAlreadyPaid;
        }
        if (!empty($skippedNotInDb)) {
            $uebersprungenDetails['Unbekannter Code in CSV (Tippfehler?)'] = $skippedNotInDb;
        }

        $fehlerhaftDetails = [];
        if (!empty($fehlerhaftPartial)) {
            $fehlerhaftDetails['Zu geringer Betrag'] = $fehlerhaftPartial;
        }
        if (!empty($fehlerhaftStorage)) {
            $fehlerhaftDetails['Speicherfehler'] = $fehlerhaftStorage;
        }
        if (!empty($unlesbareZeilenDetails)) {
            $fehlerhaftDetails['CSV-Lesefehler'] = $unlesbareZeilenDetails;
        }

        $erfCount = \count($erfolgreichDetails);
        $uebCount = \count($skippedNotInCsv) + \count($skippedAlreadyPaid) + \count($skippedNotInDb);
        $fehlCount = \count($fehlerhaftPartial) + \count($fehlerhaftStorage) + \count($unlesbareZeilenDetails) + \count($sammelTransfers);

        $this->writeLog("Abgleich komplett. Resultat -> Erfolgreich: {$erfCount} | Übersprungen: {$uebCount} | Fehlerhaft: {$fehlCount}\n---", $runLogs);

        $archiveEnabled = (bool) $this->config->get('bank_import_archive_enabled', false);
        if ($archiveEnabled) {
            $this->createArchiveZip($filePath, $runLogs);
        }

        @\unlink($filePath);

        return [
            'success' => true,
            'erfolgreich_count' => $erfCount,
            'uebersprungen_count' => $uebCount,
            'fehlerhaft_count' => $fehlCount,
            'erfolgreich_details' => $erfolgreichDetails,
            'uebersprungen_details' => $uebersprungenDetails,
            'fehlerhaft_details' => $fehlerhaftDetails,
            'sammel_transfers' => $sammelTransfers,
        ];
    }

    private function writeLog(string $message, array &$runLogs = []): void
    {
        $logDir = \rtrim((string) $this->config->get('root_path', ''), '/\\') . '/logs';
        if (!\is_dir($logDir)) {
            @\mkdir($logDir, 0o755, true);
        }

        $logFile = $logDir . '/bank_import.log';
        $timestamp = \date('d-M-Y H:i:s e');
        $formattedMessage = "[$timestamp] BankImport: $message\n";

        @\file_put_contents($logFile, $formattedMessage, \FILE_APPEND | \LOCK_EX);

        $runLogs[] = $formattedMessage;
    }

    private function createArchiveZip(string $csvFilePath, array $logs): void
    {
        $root = \rtrim((string) $this->config->get('root_path', ''), '/\\');
        $archiveDir = $root . '/storage/bank_imports';
        if (!\is_dir($archiveDir)) {
            @\mkdir($archiveDir, 0o755, true);
        }

        $htaccessPath = $archiveDir . '/.htaccess';
        if (!\file_exists($htaccessPath)) {
            @\file_put_contents($htaccessPath, "Order allow,deny\nDeny from all\n");
        }

        $timestamp = \date('Ymd_His');
        $uniq = \uniqid();
        $zipFilename = $archiveDir . '/import_' . $timestamp . '_' . $uniq . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($csvFilePath, 'import_' . $timestamp . '.csv');
            $zip->addFromString('import_' . $timestamp . '.log', \implode('', $logs));

            $password = (string) $this->config->get('bank_import_zip_password', '');
            if ($password !== '') {
                $zip->setPassword($password);
                $zip->setEncryptionName('import_' . $timestamp . '.csv', ZipArchive::EM_AES_256);
                $zip->setEncryptionName('import_' . $timestamp . '.log', ZipArchive::EM_AES_256);
            }
            $zip->close();
        }
    }

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

        $encoding = \mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-15', 'ISO-8859-1', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = \mb_convert_encoding($content, 'UTF-8', $encoding);
        } elseif (!$encoding) {
            $content = \mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $content = \str_replace(["\r\n", "\r"], "\n", $content);
        \file_put_contents($filePath, $content);
    }

    private function detectDelimiter(string $filePath): string
    {
        $handle = \fopen($filePath, 'r');
        if ($handle === false) {
            return ';';
        }

        $firstLine = \fgets($handle);
        \fclose($handle);

        if ($firstLine === false) {
            return ';';
        }

        $delimiters = [
            ';' => \substr_count($firstLine, ';'),
            ',' => \substr_count($firstLine, ','),
            "\t" => \substr_count($firstLine, "\t"),
            '|' => \substr_count($firstLine, '|'),
        ];

        \arsort($delimiters);

        return (string) \array_key_first($delimiters);
    }

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
