<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\Contracts\Config\ConfigInterface;
use App\Modules\Permit\Application\UseCases\MarkPermitAsPaid\MarkPermitAsPaidCommand;
use App\Modules\Permit\Application\UseCases\MarkPermitAsPaid\MarkPermitAsPaidHandler;
use DateTimeImmutable;
use DomainException;
use Exception;
use League\Csv\Reader;
use PDO;
use ZipArchive;

/**
 * Orchestriert den Bank-Import. Da wir ein ResultDTO zurückgeben, implementiert
 * dieser Use-Case ganz pragmatisch NICHT das strenge CommandHandlerInterface (void).
 */
final readonly class ProcessBankImportHandler
{
    public function __construct(
        private PDO $pdo,
        private MarkPermitAsPaidHandler $markPaidHandler,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Da Handler per Interface void zurückgeben, nutzen wir ein lokales State-Feld oder werfen Exceptions.
     * Da die Architektur aber ein Result-Array im Controller erwartet hat, nutzen wir einen Trick:
     * Wir geben hier ausnahmsweise das DTO zurück, da es ein Workflow-Orchestrator ist.
     */
    public function handle(ProcessBankImportCommand $command): BankImportResultDto
    {
        $runLogs = []; // Zustand wandert in die Methode, Klasse bleibt readonly!

        if (!\file_exists($command->tempFile)) {
            $this->writeLog("Fehler: Die Datei '{$command->tempFile}' konnte nicht gefunden werden.", $runLogs);

            return new BankImportResultDto(false, 'Datei konnte nicht gefunden werden.');
        }

        $this->writeLog('Starte Dateireinigung und Verarbeitung der CSV via League/Csv...', $runLogs);
        $this->prepareAndNormalizeFile($command->tempFile);

        try {
            $stream = \fopen($command->tempFile, 'r');
            if ($stream === false) {
                return new BankImportResultDto(false, 'Datei konnte nicht zum Lesen geöffnet werden.');
            }

            $csv = Reader::from($stream);
            $csv->setDelimiter($this->detectDelimiter($command->tempFile));
            $csv->setHeaderOffset(null);
        } catch (Exception $e) {
            $this->writeLog('Fehler beim Initialisieren des CSV Readers: ' . $e->getMessage(), $runLogs);

            return new BankImportResultDto(false, 'CSV Format ist ungültig oder beschädigt.');
        }

        // --- HIGH-SPEED PDO FETCH ---
        $stmt = $this->pdo->query('SELECT code, name, kennzeichen, status, preis FROM permits');
        $allCodes = [];
        $unpaidCodes = [];
        $unpaidPlates = [];
        $prices = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $c = $row['code'];
            $allCodes[$c] = true;
            $prices[$c] = (float) $row['preis'];

            if ($row['status'] === 'bezahlt') {
                continue;
            }

            $unpaidCodes[$c] = $row['name'];
            $unpaidPlates[$c] = $row['kennzeichen'];
        }
        // -----------------------------

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

        $missingUnpaidCodes = $unpaidCodes;
        $rowNumber = 0;

        foreach ($csv->getRecords() as $index => $row) {
            ++$rowNumber;
            if ($index === 0) {
                continue;
            }
            if (\count($row) === 1 && ($row[0] === null || \trim((string) $row[0]) === '')) {
                continue;
            }

            if (!isset($row[$command->idColumn], $row[$command->amountColumn], $row[$command->dateColumn])) {
                $colCount = \count($row);
                $errorMsg = "Zeile {$rowNumber} (Spalten fehlen, nur {$colCount} vorhanden)";
                $this->writeLog("[Zeile {$rowNumber}] Fehler: Benötigte Spalten fehlen. Verfügbare Spalten: {$colCount}.", $runLogs);
                $unlesbareZeilenDetails[] = $errorMsg;
                continue;
            }

            $verwendungszweck = (string) $row[$command->idColumn];
            $betragRaw = (string) $row[$command->amountColumn];
            $datumRaw = (string) $row[$command->dateColumn];
            $zweckUpper = \strtoupper($verwendungszweck);

            $gefundeneCodes = [];
            $matchMethodsForLine = [];
            $matchMethodsMap = [];

            // PRIO 1: Exakte Suche
            if (\preg_match('/EFG-([A-Z0-9]{6,8})/i', $zweckUpper, $efgMatches)) {
                $extractedShortCode = $efgMatches[1];
                foreach ($unpaidCodes as $fullUnpaidCode => $ownerName) {
                    $codeParts = \explode('-', $fullUnpaidCode);
                    $dbShortCode = \end($codeParts);
                    if ($dbShortCode === $extractedShortCode) {
                        $gefundeneCodes[] = $fullUnpaidCode;
                        $matchMethodsForLine[] = 'Exaktes Muster (EFG-Code)';
                        $matchMethodsMap[$fullUnpaidCode] = 'Exaktes Muster';
                        break;
                    }
                }
            }

            // PRIO 2: Fallback (Vorhandensein der ID im Text)
            if ($gefundeneCodes === []) {
                foreach ($unpaidCodes as $fullUnpaidCode => $ownerName) {
                    $codeParts = \explode('-', $fullUnpaidCode);
                    $shortCode = \end($codeParts);
                    if (!\str_contains($zweckUpper, $shortCode)) {
                        continue;
                    }

                    $gefundeneCodes[] = $fullUnpaidCode;
                    $matchMethodsForLine[] = 'Direktsuche (Unbezahlt)';
                    $matchMethodsMap[$fullUnpaidCode] = 'Direktsuche';
                }
            }

            // PRIO 3: Regex Fallback
            if ($gefundeneCodes === []) {
                if (\preg_match_all('/([ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6,8})/', $zweckUpper, $matches)) {
                    foreach ($matches[1] as $shortCodeCandidate) {
                        foreach ($allCodes as $fullCode => $dummy) {
                            if (\str_ends_with($fullCode, $shortCodeCandidate)) {
                                $gefundeneCodes[] = $fullCode;
                                $matchMethodsForLine[] = 'Regex Fallback (Bereits im System)';
                                $matchMethodsMap[$fullCode] = 'Regex: Bezahlt';
                                break;
                            }
                        }
                    }
                }
            }

            $cleanAmount = \str_replace('.', '', $betragRaw);
            $cleanAmount = \str_replace(',', '.', $cleanAmount);
            $ueberwiesenerBetrag = (float) $cleanAmount;
            $anomalyId = 'sam_' . \md5($datumRaw . $betragRaw . $verwendungszweck);

            // PRIO 4: Fallback Kennzeichen-Suche
            $gefundeneKennzeichen = [];
            if ($gefundeneCodes === []) {
                $zweckNormalized = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', $zweckUpper);
                foreach ($unpaidPlates as $unpaidCode => $plate) {
                    if (empty($plate)) {
                        continue;
                    }

                    $plateNormalized = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', \strtoupper($plate));
                    if (\strlen($plateNormalized) < 4 || !\str_contains($zweckNormalized, $plateNormalized)) {
                        continue;
                    }

                    $gefundeneKennzeichen[$unpaidCode] = $plate;
                }
            }

            if ($gefundeneCodes === [] && $gefundeneKennzeichen === []) {
                $this->writeLog("[Zeile {$rowNumber}] Info: Kein System-Code und kein Kennzeichen gefunden. Rohdaten Zweck: '{$verwendungszweck}'", $runLogs);
                continue;
            }

            if ($gefundeneCodes === [] && $gefundeneKennzeichen !== []) {
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

            if (!isset($allCodes[$permitId])) {
                $this->writeLog("[Code {$permitId}] Übersprungen: Code existiert nicht in der Datenbank (Erkannt via: {$method}).", $runLogs);
                $skippedNotInDb[] = $permitId;
                continue;
            }

            $ownerName = $unpaidCodes[$permitId] ?? 'Unbekannt (Bereits bezahlt)';

            if (!isset($unpaidCodes[$permitId])) {
                $this->writeLog("[Code {$permitId}] Übersprungen: Genehmigung für '{$ownerName}' ist im System bereits als BEZAHLT markiert (Erkannt via: {$method}).", $runLogs);
                $skippedAlreadyPaid[] = "{$permitId} ({$ownerName})";
                continue;
            }

            $sollBetrag = \round($prices[$permitId], 2);
            $istBetrag = \round($gesamtsumme, 2);

            $sollFormatted = \number_format($sollBetrag, 2, ',', '.') . ' €';
            $istFormatted = \number_format($istBetrag, 2, ',', '.') . ' €';

            if ($istBetrag >= $sollBetrag) {
                $datumRaw = (string) $letztesDatumPerPermit[$permitId];
                $formatierterTag = $this->parseDate($datumRaw);
                $grund = 'Automatisch via Bank-Import freigeschaltet (Summe der Zahlungen: ' . $istFormatted . ')';

                try {
                    // Domain-Kommunikation! Das Finance-Modul triggert einen Use-Case im Permit-Modul.
                    $this->markPaidHandler->handle(new MarkPermitAsPaidCommand($permitId, $grund, $formatierterTag));

                    $this->writeLog("[Code {$permitId}] ERFOLG: Zahlung von {$istBetrag} € für '{$ownerName}' (Soll: {$sollBetrag} €) verbucht (Erkannt via: {$method}).", $runLogs);
                    $erfolgreichDetails[] = "{$permitId} ({$ownerName})";
                } catch (DomainException) {
                    $this->writeLog("[Code {$permitId}] KRITISCHER FEHLER: Konnte Status für '{$ownerName}' nicht auf Bezahlt setzen (Erkannt via: {$method}).", $runLogs);
                    $fehlerhaftStorage[] = "{$permitId} ({$ownerName})";
                }
            } else {
                $this->writeLog("[Code {$permitId}] FEHLER: Betrag reicht für '{$ownerName}' nicht aus. (Soll: {$sollBetrag} €, Ist: {$istBetrag} €) (Erkannt via: {$method}).", $runLogs);
                $fehlerhaftPartial[] = "{$permitId} ({$ownerName}: {$istFormatted} statt {$sollFormatted})";
            }
        }

        // Bündelung für das Frontend
        $erfolgreichDetails = \array_values(\array_unique($erfolgreichDetails));
        $skippedNotInCsv = \array_values(\array_unique($skippedNotInCsv));
        $skippedAlreadyPaid = \array_values(\array_unique($skippedAlreadyPaid));
        $skippedNotInDb = \array_values(\array_unique($skippedNotInDb));
        $fehlerhaftPartial = \array_values(\array_unique($fehlerhaftPartial));
        $fehlerhaftStorage = \array_values(\array_unique($fehlerhaftStorage));
        $unlesbareZeilenDetails = \array_values(\array_unique($unlesbareZeilenDetails));

        $uebersprungenDetails = [];
        if ($skippedNotInCsv !== []) {
            $uebersprungenDetails['Fehlt auf Auszug (in CSV)'] = $skippedNotInCsv;
        }
        if ($skippedAlreadyPaid !== []) {
            $uebersprungenDetails['Bereits verbucht'] = $skippedAlreadyPaid;
        }
        if ($skippedNotInDb !== []) {
            $uebersprungenDetails['Unbekannter Code in CSV'] = $skippedNotInDb;
        }

        $fehlerhaftDetails = [];
        if ($fehlerhaftPartial !== []) {
            $fehlerhaftDetails['Zu geringer Betrag'] = $fehlerhaftPartial;
        }
        if ($fehlerhaftStorage !== []) {
            $fehlerhaftDetails['Speicherfehler'] = $fehlerhaftStorage;
        }
        if ($unlesbareZeilenDetails !== []) {
            $fehlerhaftDetails['CSV-Lesefehler'] = $unlesbareZeilenDetails;
        }

        $erfCount = \count($erfolgreichDetails);
        $uebCount = \count($skippedNotInCsv) + \count($skippedAlreadyPaid) + \count($skippedNotInDb);
        $fehlCount = \count($fehlerhaftPartial) + \count($fehlerhaftStorage) + \count($unlesbareZeilenDetails) + \count($sammelTransfers);

        $this->writeLog("Abgleich komplett. Resultat -> Erfolgreich: {$erfCount} | Übersprungen: {$uebCount} | Fehlerhaft: {$fehlCount}\n---", $runLogs);

        if ((bool) $this->config->get('bank_import_archive_enabled', false)) {
            $this->createArchiveZip($command->tempFile, $runLogs);
        }

        @\unlink($command->tempFile);

        return new BankImportResultDto(
            success: true,
            message: 'Import abgeschlossen.',
            successCount: $erfCount,
            skippedCount: $uebCount,
            errorCount: $fehlCount,
            successDetails: $erfolgreichDetails,
            skippedDetails: $uebersprungenDetails,
            errorDetails: $fehlerhaftDetails,
            collectiveTransfers: $sammelTransfers,
        );
    }

    private function writeLog(string $message, array &$runLogs): void
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
        if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

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

    private function prepareAndNormalizeFile(string $filePath): void
    {
        $content = \file_get_contents($filePath);
        if (!\is_string($content) || $content === '') {
            return;
        }
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
