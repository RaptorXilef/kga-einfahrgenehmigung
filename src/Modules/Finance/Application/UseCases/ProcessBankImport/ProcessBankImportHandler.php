<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\Modules\Finance\Application\Contracts\UnpaidPermitProviderInterface;
use App\SharedKernel\Domain\Event\BankPaymentAssignedEvent;
use DateTimeImmutable;
use DomainException;
use League\Csv\Reader;

/**
 * Orchestriert den Bank-Import als Application Service und gibt direkt das Report-DTO zurück.
 */
final readonly class ProcessBankImportHandler
{
    public function __construct(
        private UnpaidPermitProviderInterface $unpaidPermitProvider,
        private ConfigInterface $config,
        private EventDispatcherInterface $eventDispatcher,
        private BankImportInfrastructureInterface $infrastructure,
    ) {
    }

    public function handle(ProcessBankImportCommand $command): BankImportResultDto
    {
        $runLogs = [];

        $this->infrastructure->writeLog('Starte Dateireinigung und Verarbeitung der CSV...', $runLogs);
        $csv = $this->infrastructure->normalizeAndOpenCsv($command->tempFile);

        if (!$csv instanceof Reader) {
            $this->infrastructure->writeLog("Fehler: Die Datei '{$command->tempFile}' ist ungültig oder konnte nicht geöffnet werden.", $runLogs);

            return new BankImportResultDto(false, 'Datei konnte nicht gefunden oder gelesen werden.');
        }

        // --- DECOUPLED DATA FETCH VIA DTO ---
        $permitData = $this->unpaidPermitProvider->getPermitDataForImport();
        $allCodes = $permitData->allCodes;
        $unpaidCodes = $permitData->unpaidCodes;
        $unpaidPlates = $permitData->unpaidPlates;
        $prices = $permitData->prices;
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
                $this->infrastructure->writeLog("[Zeile {$rowNumber}] Fehler: Benötigte Spalten fehlen. Verfügbare Spalten: {$colCount}.", $runLogs);
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
                $this->infrastructure->writeLog("[Zeile {$rowNumber}] Info: Kein System-Code und kein Kennzeichen gefunden. Rohdaten Zweck: '{$verwendungszweck}'", $runLogs);
                continue;
            }

            if ($gefundeneCodes === [] && $gefundeneKennzeichen !== []) {
                $matchedCodes = \array_keys($gefundeneKennzeichen);
                $codesStr = \implode(', ', $matchedCodes);
                $platesStr = \implode(', ', \array_values($gefundeneKennzeichen));

                $this->infrastructure->writeLog("[Zeile {$rowNumber}] HINWEIS: Kein Code, aber Kennzeichen [{$platesStr}] für Codes [{$codesStr}] gefunden. Ausgesteuert zur manuellen Prüfung.", $runLogs);

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
                $this->infrastructure->writeLog("[Zeile {$rowNumber}] FEHLER: Mehrere Codes in einer Überweisung gefunden [{$codesStr}]. Wird zur manuellen Prüfung ausgesteuert.", $runLogs);

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

            $this->infrastructure->writeLog("[Zeile {$rowNumber}] Info: Code(s) erkannt: [{$codesStr}] via {$methodStr}. Lese Betrag: {$ueberwiesenerBetrag} €", $runLogs);

            foreach ($gefundeneCodes as $permitIdStr) {
                $aggregierteZahlungen[$permitIdStr] ??= 0.0;
                $aggregierteZahlungen[$permitIdStr] += $ueberwiesenerBetrag;
                $letztesDatumPerPermit[$permitIdStr] = $datumRaw;
                $methodenPerPermit[$permitIdStr] = $matchMethodsMap[$permitIdStr] ?? 'Unbekannt';
                unset($missingUnpaidCodes[$permitIdStr]);
            }
        }

        foreach ($missingUnpaidCodes as $missingCode => $ownerName) {
            $this->infrastructure->writeLog("[Code {$missingCode}] Fehlt in CSV: Unbezahlte Genehmigung für '{$ownerName}' wurde nicht gefunden.", $runLogs);
            $skippedNotInCsv[] = "{$missingCode} ({$ownerName})";
        }

        $this->infrastructure->writeLog('Dateidurchlauf beendet. Starte Datenbank-Abgleich...', $runLogs);

        foreach ($aggregierteZahlungen as $permitId => $gesamtsumme) {
            $method = $methodenPerPermit[$permitId] ?? 'Unbekannt';

            if (!isset($allCodes[$permitId])) {
                $this->infrastructure->writeLog("[Code {$permitId}] Übersprungen: Code existiert nicht in der Datenbank (Erkannt via: {$method}).", $runLogs);
                $skippedNotInDb[] = $permitId;
                continue;
            }

            $ownerName = $unpaidCodes[$permitId] ?? 'Unbekannt (Bereits bezahlt)';

            if (!isset($unpaidCodes[$permitId])) {
                $this->infrastructure->writeLog("[Code {$permitId}] Übersprungen: Genehmigung für '{$ownerName}' ist im System bereits als BEZAHLT markiert (Erkannt via: {$method}).", $runLogs);
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
                    $this->eventDispatcher->dispatch(new BankPaymentAssignedEvent($permitId, $grund, $formatierterTag));
                    $this->infrastructure->writeLog("[Code {$permitId}] ERFOLG: Zahlung von {$istBetrag} € für '{$ownerName}' (Soll: {$sollBetrag} €) verbucht (Erkannt via: {$method}).", $runLogs);
                    $erfolgreichDetails[] = "{$permitId} ({$ownerName})";
                } catch (DomainException) {
                    $this->infrastructure->writeLog("[Code {$permitId}] KRITISCHER FEHLER: Konnte Status für '{$ownerName}' nicht auf Bezahlt setzen (Erkannt via: {$method}).", $runLogs);
                    $fehlerhaftStorage[] = "{$permitId} ({$ownerName})";
                }
            } else {
                $this->infrastructure->writeLog("[Code {$permitId}] FEHLER: Betrag reicht für '{$ownerName}' nicht aus. (Soll: {$sollBetrag} €, Ist: {$istBetrag} €) (Erkannt via: {$method}).", $runLogs);
                $fehlerhaftPartial[] = "{$permitId} ({$ownerName}: {$istFormatted} statt {$sollFormatted})";
            }
        }

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

        $this->infrastructure->writeLog("Abgleich komplett. Resultat -> Erfolgreich: {$erfCount} | Übersprungen: {$uebCount} | Fehlerhaft: {$fehlCount}\n---", $runLogs);

        if ((bool) $this->config->get('bank_import_archive_enabled', false)) {
            $this->infrastructure->createArchiveZip($command->tempFile, $runLogs);
        }

        $this->infrastructure->cleanupTempFile($command->tempFile);

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
