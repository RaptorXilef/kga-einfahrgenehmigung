<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\Modules\Finance\Application\Contracts\UnpaidPermitProviderInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use App\SharedKernel\Domain\Event\BankPaymentAssignedEvent;
use DateTimeImmutable;
use DomainException;
use League\Csv\Reader;
use Override;

/**
 * Orchestriert den Bank-Import mit einer 4-stufigen Erkennungskaskade pro Zeile,
 * optionalem 6-Stellen-Suffix-Matching für 8-stellige Codes sowie Betrugs-/Doppelzahlungserkennung.
 *
 * @implements CommandWithResultHandlerInterface<ProcessBankImportCommand, BankImportResultDto>
 */
final readonly class ProcessBankImportHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private UnpaidPermitProviderInterface $unpaidPermitProvider,
        private ConfigInterface $config,
        private EventDispatcherInterface $eventDispatcher,
        private BankImportInfrastructureInterface $infrastructure,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param ProcessBankImportCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): BankImportResultDto
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
        $prices = $permitData->prices;
        $records = $permitData->records;

        $allowLegacy6CharSuffix = $this->config->getBool('allow_legacy_6char_suffix_match', true);

        $aggregierteZahlungen = [];
        $letztesDatumPerPermit = [];
        $letzterZweckPerPermit = [];
        $letzterSenderPerPermit = [];
        $letzterExtrahierterCodePerPermit = [];
        $methodenPerPermit = [];

        $erfolgreichDetails = [];
        $skippedNotInCsv = [];
        $skippedAlreadyPaid = [];
        $skippedNotInDb = [];
        $fehlerhaftPartial = [];
        $fehlerhaftOverpaid = [];
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

            $verwendungszweck = \trim((string) $row[$command->idColumn]);
            $betragRaw = \trim((string) $row[$command->amountColumn]);
            $datumRaw = \trim((string) $row[$command->dateColumn]);
            $absenderRaw = isset($row[$command->senderColumn]) ? \trim((string) $row[$command->senderColumn]) : '';
            $waehrungRaw = isset($row[$command->currencyColumn]) ? \strtoupper(\trim((string) $row[$command->currencyColumn])) : 'EUR';
            $waehrung = $waehrungRaw !== '' ? $waehrungRaw : 'EUR';
            $isEur = \in_array($waehrung, ['EUR', '€', 'EURO'], true);

            $parsedDate = $this->parseDate($datumRaw);
            $ueberwiesenerBetrag = $this->parseAmount($betragRaw);
            $anomalyId = 'sam_' . \md5($datumRaw . $betragRaw . $verwendungszweck . $absenderRaw . $rowNumber);

            // =====================================================================
            // PRIO 1: Direktsuche nach Permit-Codes (6- & 8-stellig + 6er-Suffix)
            // =====================================================================
            $prio1Matches = $this->findPrio1CodeMatches($verwendungszweck, $records, $allowLegacy6CharSuffix);

            if ($prio1Matches !== []) {
                $matchedFullCodes = \array_keys($prio1Matches);
                foreach ($matchedFullCodes as $mc) {
                    unset($missingUnpaidCodes[$mc]);
                }

                // Fall 1A: Mehr als ein Code im selben Betreff (egal ob offen oder bezahlt) -> IMMER To-Do!
                if (\count($prio1Matches) > 1) {
                    $codesStr = \implode(', ', $matchedFullCodes);
                    $this->infrastructure->writeLog("[Zeile {$rowNumber}] PRIO 1 (Sammelüberweisung): Mehrere Codes im Betreff erkannt [{$codesStr}]. Ausgesteuert in To-Do-Liste.", $runLogs);

                    $sammelTransfers[] = $this->buildTodoItem(
                        id: $anomalyId,
                        date: $parsedDate,
                        amount: $ueberwiesenerBetrag,
                        currency: $waehrung,
                        purpose: $verwendungszweck,
                        senderName: $absenderRaw,
                        type: 'multi_code',
                        reasonTitle: 'Mehr als eine Permit-ID im Betreff (' . \count($prio1Matches) . ' Codes erkannt)',
                        reasonHint: 'Bitte prüfen Sie die Einzelbeträge und schalten Sie die passenden Genehmigungen manuell frei.',
                        matchedCodes: $matchedFullCodes,
                        extractedMap: $prio1Matches,
                        allRecords: $records,
                    );
                    continue;
                }

                // Genau 1 Code im Betreff erkannt
                $singleFullCode = $matchedFullCodes[0];
                $matchMeta = $prio1Matches[$singleFullCode];
                $record = $records[$singleFullCode];
                $status = $record['status'];

                // Währungs-Check (Spalte 16)
                if (!$isEur) {
                    $this->infrastructure->writeLog("[Zeile {$rowNumber}] PRIO 1: Code {$singleFullCode} erkannt, aber Fremdwährung '{$waehrung}' (statt EUR). Ausgesteuert in To-Do-Liste.", $runLogs);

                    $sammelTransfers[] = $this->buildTodoItem(
                        id: $anomalyId,
                        date: $parsedDate,
                        amount: $ueberwiesenerBetrag,
                        currency: $waehrung,
                        purpose: $verwendungszweck,
                        senderName: $absenderRaw,
                        type: 'currency_mismatch',
                        reasonTitle: "Fremdwährung erkannt ({$waehrung} statt EUR)",
                        reasonHint: 'Überweisung erfolgte nicht in Euro. Bitte tatsächlichen gutgeschriebenen EUR-Betrag prüfen.',
                        matchedCodes: [$singleFullCode],
                        extractedMap: $prio1Matches,
                        allRecords: $records,
                    );
                    continue;
                }

                // Fall 1B: Zahlung auf eine bereits STORNIERTE Genehmigung!
                if ($status === 'storniert') {
                    $this->infrastructure->writeLog("[Zeile {$rowNumber}] WARNUNG: Zahlungseingang ({$ueberwiesenerBetrag} EUR) auf STORNIERTE Genehmigung {$singleFullCode}. Ausgesteuert in To-Do-Liste.", $runLogs);

                    $sammelTransfers[] = $this->buildTodoItem(
                        id: $anomalyId,
                        date: $parsedDate,
                        amount: $ueberwiesenerBetrag,
                        currency: $waehrung,
                        purpose: $verwendungszweck,
                        senderName: $absenderRaw,
                        type: 'cancelled_paid',
                        reasonTitle: 'Zahlung auf STORNIERTE Genehmigung eingegangen!',
                        reasonHint: 'WICHTIG: Diese Genehmigung wurde storniert. Bitte manuell prüfen, ob das Geld zurücküberwiesen werden muss oder bereits zurücküberwiesen wurde!',
                        matchedCodes: [$singleFullCode],
                        extractedMap: $prio1Matches,
                        allRecords: $records,
                    );
                    continue;
                }

                // Fall 1C: Code ist im System bereits als BEZAHLT markiert (Betrugs- & Vorlagen-Detektor)
                if ($status === 'bezahlt') {
                    $paidAtDate = $record['bezahltAmFormatted'];

                    // Wenn das Buchungsdatum identisch ist (oder historisch leer), ist es derselbe Bankumsatz -> regulär überspringen!
                    if ($paidAtDate === null || $paidAtDate === $parsedDate) {
                        $this->infrastructure->writeLog("[Code {$singleFullCode}] Übersprungen: Genehmigung für '{$record['name']}' wurde bereits am {$paidAtDate} verbucht.", $runLogs);
                        $skippedAlreadyPaid[] = "{$singleFullCode} ({$record['name']})";
                        continue;
                    }

                    // Abweichendes Überweisungsdatum -> Pächter hat denselben Code an einem anderen Tag erneut bezahlt!
                    $this->infrastructure->writeLog("[Zeile {$rowNumber}] WARNUNG (Doppelzahlung/Vorlage): Code {$singleFullCode} wurde bereits am {$paidAtDate} bezahlt, aber erneut überwiesen am {$parsedDate}.", $runLogs);

                    $sammelTransfers[] = $this->buildTodoItem(
                        id: $anomalyId,
                        date: $parsedDate,
                        amount: $ueberwiesenerBetrag,
                        currency: $waehrung,
                        purpose: $verwendungszweck,
                        senderName: $absenderRaw,
                        type: 'duplicate_paid',
                        reasonTitle: "Erneute Zahlung auf bereits bezahlte Permit-ID (Bereits am {$paidAtDate} bezahlt, erneut überwiesen am {$parsedDate})",
                        reasonHint: 'Prüfen Sie in der untenstehenden Liste, ob der Pächter eine alte Überweisungsvorlage für einen neuen offenen Antrag genutzt hat oder ob eine Doppelzahlung vorliegt.',
                        matchedCodes: [$singleFullCode],
                        extractedMap: $prio1Matches,
                        allRecords: $records,
                    );
                    continue;
                }

                // Fall 1D: Genau 1 offener Code in EUR -> Zahlung für den Abgleich vormerken
                $this->infrastructure->writeLog("[Zeile {$rowNumber}] PRIO 1: Offener Code [{$singleFullCode}] erkannt via {$matchMeta['method']}. Betrag: {$ueberwiesenerBetrag} €", $runLogs);

                $aggregierteZahlungen[$singleFullCode] ??= 0.0;
                $aggregierteZahlungen[$singleFullCode] += $ueberwiesenerBetrag;
                $letztesDatumPerPermit[$singleFullCode] = $parsedDate;
                $letzterZweckPerPermit[$singleFullCode] = $verwendungszweck;
                $letzterSenderPerPermit[$singleFullCode] = $absenderRaw;
                $letzterExtrahierterCodePerPermit[$singleFullCode] = $matchMeta['extracted'];
                $methodenPerPermit[$singleFullCode] = $matchMeta['method'];
                continue;
            }

            // =====================================================================
            // PRIO 2: Kennzeichen-Suche im Betreff (Nur von Unbezahlten, falls PRIO 1 leer)
            // =====================================================================
            $prio2PlateMatches = $this->findPrio2LicensePlateMatches($verwendungszweck, $records);
            if ($prio2PlateMatches !== []) {
                $matchedCodes = \array_keys($prio2PlateMatches);
                $platesStr = \implode(', ', \array_unique(\array_values($prio2PlateMatches)));
                $codesStr = \implode(', ', $matchedCodes);

                $this->infrastructure->writeLog("[Zeile {$rowNumber}] PRIO 2 (Kennzeichen): Keine ID, aber Kennzeichen [{$platesStr}] für offene Permit(s) [{$codesStr}] erkannt. In To-Do-Liste.", $runLogs);

                foreach ($matchedCodes as $mc) {
                    unset($missingUnpaidCodes[$mc]);
                }

                $sammelTransfers[] = $this->buildTodoItem(
                    id: $anomalyId,
                    date: $parsedDate,
                    amount: $ueberwiesenerBetrag,
                    currency: $waehrung,
                    purpose: $verwendungszweck,
                    senderName: $absenderRaw,
                    type: 'kennzeichen',
                    reasonTitle: "Keine Permit-ID im Betreff - Erkannt über Kfz-Kennzeichen ({$platesStr})",
                    reasonHint: 'Bitte gleichen Sie Kennzeichen, Zeitraum und Betrag mit den unten aufgelisteten Genehmigungen ab.',
                    matchedCodes: $matchedCodes,
                    extractedMap: [],
                    allRecords: $records,
                );
                continue;
            }

            // =====================================================================
            // PRIO 3: Vollständiger Name in Betreff (Col 5) ODER Auftraggeber (Col 12)
            // =====================================================================
            $prio3NameMatches = $this->findPrio3NameMatches($verwendungszweck, $absenderRaw, $records);
            if ($prio3NameMatches !== []) {
                $matchedCodes = \array_keys($prio3NameMatches);
                $sourcesStr = \implode(', ', \array_unique(\array_values($prio3NameMatches)));
                $codesStr = \implode(', ', $matchedCodes);

                $this->infrastructure->writeLog("[Zeile {$rowNumber}] PRIO 3 (Name): Keine ID/Kennzeichen, aber Name erkannt ({$sourcesStr}) für offene Permit(s) [{$codesStr}]. In To-Do-Liste.", $runLogs);

                foreach ($matchedCodes as $mc) {
                    unset($missingUnpaidCodes[$mc]);
                }

                $sammelTransfers[] = $this->buildTodoItem(
                    id: $anomalyId,
                    date: $parsedDate,
                    amount: $ueberwiesenerBetrag,
                    currency: $waehrung,
                    purpose: $verwendungszweck,
                    senderName: $absenderRaw,
                    type: 'name',
                    reasonTitle: "Keine Permit-ID im Betreff - Erkannt über vollständigen Namen ({$sourcesStr})",
                    reasonHint: 'Bitte prüfen Sie anhand der Historie, ob bereits bezahlte oder andere offene Anträge für diese Person existieren.',
                    matchedCodes: $matchedCodes,
                    extractedMap: [],
                    allRecords: $records,
                );
                continue;
            }

            // =====================================================================
            // PRIO 4: Parzellen-Suche im Betreff (Format: Parzelle 661 / 0661 / 0061 / 61)
            // =====================================================================
            $prio4PlotMatches = $this->findPrio4PlotMatches($verwendungszweck, $records);
            if ($prio4PlotMatches !== []) {
                $matchedCodes = \array_keys($prio4PlotMatches);
                $plotsStr = \implode(', ', \array_unique(\array_values($prio4PlotMatches)));
                $codesStr = \implode(', ', $matchedCodes);

                $this->infrastructure->writeLog("[Zeile {$rowNumber}] PRIO 4 (Parzelle): Nur Parzelle [{$plotsStr}] im Betreff erkannt für offene Permit(s) [{$codesStr}]. In To-Do-Liste.", $runLogs);

                foreach ($matchedCodes as $mc) {
                    unset($missingUnpaidCodes[$mc]);
                }

                $sammelTransfers[] = $this->buildTodoItem(
                    id: $anomalyId,
                    date: $parsedDate,
                    amount: $ueberwiesenerBetrag,
                    currency: $waehrung,
                    purpose: $verwendungszweck,
                    senderName: $absenderRaw,
                    type: 'parzelle',
                    reasonTitle: "Keine Permit-ID, kein Kennzeichen & kein Name - Nur über Parzelle ({$plotsStr}) erkannt",
                    reasonHint: 'Vorsicht bei Parzellen-Treffern ohne Code: Prüfen Sie alle unten gelisteten offenen und bezahlten Genehmigungen dieser Parzelle.',
                    matchedCodes: $matchedCodes,
                    extractedMap: [],
                    allRecords: $records,
                );
                continue;
            }

            $this->infrastructure->writeLog("[Zeile {$rowNumber}] Info: Kein Code, kein Kennzeichen, kein Name und keine Parzelle einer offenen Genehmigung gefunden. Rohdaten Zweck: '{$verwendungszweck}'", $runLogs);
        }

        foreach ($missingUnpaidCodes as $missingCode => $ownerName) {
            $this->infrastructure->writeLog("[Code {$missingCode}] Fehlt in CSV: Unbezahlte Genehmigung für '{$ownerName}' wurde nicht gefunden.", $runLogs);
            $skippedNotInCsv[] = "{$missingCode} ({$ownerName})";
        }

        $this->infrastructure->writeLog('Dateidurchlauf beendet. Starte Datenbank-Abgleich für eindeutige PRIO-1-Treffer...', $runLogs);

        foreach ($aggregierteZahlungen as $permitId => $gesamtsumme) {
            $method = $methodenPerPermit[$permitId] ?? 'PRIO 1';

            if (!isset($allCodes[$permitId])) {
                $this->infrastructure->writeLog("[Code {$permitId}] Übersprungen: Code existiert nicht in der Datenbank.", $runLogs);
                $skippedNotInDb[] = $permitId;
                continue;
            }

            $ownerName = $unpaidCodes[$permitId] ?? ($records[$permitId]['name'] ?? 'Unbekannt');
            $sollBetrag = \round($prices[$permitId] ?? 0.0, 2);
            $istBetrag = \round($gesamtsumme, 2);

            $sollFormatted = \number_format($sollBetrag, 2, ',', '.') . ' €';
            $istFormatted = \number_format($istBetrag, 2, ',', '.') . ' €';
            $formatierterTag = $letztesDatumPerPermit[$permitId] ?? $this->clock->now()->format('d.m.Y');
            $verwendungszweck = $letzterZweckPerPermit[$permitId] ?? '';
            $absenderName = $letzterSenderPerPermit[$permitId] ?? '';
            $extractedCode = $letzterExtrahierterCodePerPermit[$permitId] ?? $permitId;

            // 1. Exakte Übereinstimmung des Betrags -> Automatisch auf BEZAHLT setzen!
            if (\abs($istBetrag - $sollBetrag) < 0.005) {
                $grund = "Automatisch via Bank-Import freigeschaltet ({$method}, Betrag: {$istFormatted})";

                try {
                    $this->eventDispatcher->dispatch(new BankPaymentAssignedEvent(
                        $permitId,
                        $grund,
                        $formatierterTag,
                        $this->clock->now(),
                    ));
                    $this->infrastructure->writeLog("[Code {$permitId}] ERFOLG: Zahlung von {$istBetrag} € für '{$ownerName}' (Soll: {$sollBetrag} €) verbucht ({$method}).", $runLogs);
                    $erfolgreichDetails[] = "{$permitId} ({$ownerName})";
                } catch (DomainException) {
                    $this->infrastructure->writeLog("[Code {$permitId}] KRITISCHER FEHLER: Konnte Status für '{$ownerName}' nicht auf Bezahlt setzen.", $runLogs);
                    $fehlerhaftStorage[] = "{$permitId} ({$ownerName})";
                }
                continue;
            }

            // 2. Zu niedriger Betrag gezahlt -> Nicht freischalten, sondern in To-Do-Liste!
            if ($istBetrag < $sollBetrag) {
                $diffFormatted = \number_format($sollBetrag - $istBetrag, 2, ',', '.') . ' €';
                $this->infrastructure->writeLog("[Code {$permitId}] FEHLER (Unterzahlung): Gezahlt {$istBetrag} € statt {$sollBetrag} € für '{$ownerName}'. In To-Do-Liste.", $runLogs);
                $fehlerhaftPartial[] = "{$permitId} ({$ownerName}: {$istFormatted} statt {$sollFormatted})";

                $sammelTransfers[] = $this->buildTodoItem(
                    id: 'sam_under_' . \md5($permitId . $formatierterTag . $istBetrag),
                    date: $formatierterTag,
                    amount: $istBetrag,
                    currency: 'EUR',
                    purpose: $verwendungszweck,
                    senderName: $absenderName,
                    type: 'underpaid',
                    reasonTitle: "Zu niedriger Betrag gezahlt (Fehlbetrag: -{$diffFormatted})",
                    reasonHint: "Gezahlt wurden {$istFormatted}, gefordert waren {$sollFormatted}. Bitte prüfen, ob eine Teilzahlung vorliegt oder ein falscher Tarif überwiesen wurde.",
                    matchedCodes: [$permitId],
                    extractedMap: [$permitId => ['extracted' => $extractedCode, 'method' => $method]],
                    allRecords: $records,
                );
                continue;
            }

            // 3. Zu hoher Betrag gezahlt -> Nicht blind freischalten, sondern in To-Do-Liste!
            $diffFormatted = \number_format($istBetrag - $sollBetrag, 2, ',', '.') . ' €';
            $this->infrastructure->writeLog("[Code {$permitId}] WARNUNG (Überzahlung): Gezahlt {$istBetrag} € statt {$sollBetrag} € für '{$ownerName}'. In To-Do-Liste.", $runLogs);
            $fehlerhaftOverpaid[] = "{$permitId} ({$ownerName}: {$istFormatted} statt {$sollFormatted})";

            $sammelTransfers[] = $this->buildTodoItem(
                id: 'sam_over_' . \md5($permitId . $formatierterTag . $istBetrag),
                date: $formatierterTag,
                amount: $istBetrag,
                currency: 'EUR',
                purpose: $verwendungszweck,
                senderName: $absenderName,
                type: 'overpaid',
                reasonTitle: "Zu hoher Betrag gezahlt (Überzahlung: +{$diffFormatted})",
                reasonHint: "Gezahlt wurden {$istFormatted}, die erkannte Genehmigung kostet aber nur {$sollFormatted}. Prüfen Sie unten, ob der Pächter weitere offene Genehmigungen in einer Summe mitbezahlt hat.",
                matchedCodes: [$permitId],
                extractedMap: [$permitId => ['extracted' => $extractedCode, 'method' => $method]],
                allRecords: $records,
            );
        }

        $erfolgreichDetails = \array_values(\array_unique($erfolgreichDetails));
        $skippedNotInCsv = \array_values(\array_unique($skippedNotInCsv));
        $skippedAlreadyPaid = \array_values(\array_unique($skippedAlreadyPaid));
        $skippedNotInDb = \array_values(\array_unique($skippedNotInDb));
        $fehlerhaftPartial = \array_values(\array_unique($fehlerhaftPartial));
        $fehlerhaftOverpaid = \array_values(\array_unique($fehlerhaftOverpaid));
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
        if ($fehlerhaftOverpaid !== []) {
            $fehlerhaftDetails['Zu hoher Betrag (Prüfung in To-Do)'] = $fehlerhaftOverpaid;
        }
        if ($fehlerhaftStorage !== []) {
            $fehlerhaftDetails['Speicherfehler'] = $fehlerhaftStorage;
        }
        if ($unlesbareZeilenDetails !== []) {
            $fehlerhaftDetails['CSV-Lesefehler'] = $unlesbareZeilenDetails;
        }

        $erfCount = \count($erfolgreichDetails);
        $uebCount = \count($skippedNotInCsv) + \count($skippedAlreadyPaid) + \count($skippedNotInDb);
        $fehlCount = \count($fehlerhaftPartial) + \count($fehlerhaftOverpaid) + \count($fehlerhaftStorage) + \count($unlesbareZeilenDetails) + \count($sammelTransfers);

        $this->infrastructure->writeLog("Abgleich komplett. Resultat -> Erfolgreich: {$erfCount} | Übersprungen: {$uebCount} | Prüfen/Fehlerhaft: {$fehlCount}\n---", $runLogs);

        if ($this->config->getBool('bank_import_archive_enabled', false)) {
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

    /**
     * PRIO 1: Sucht im Verwendungszweck nach exakten 6-/8-stelligen Codes (offen, bezahlt, storniert)
     * sowie optional nach den letzten 6 Stellen von 8-stelligen Codes.
     *
     * @param array<string, array<string, mixed>> $records
     *
     * @return array<string, array{extracted: string, method: string}>
     */
    private function findPrio1CodeMatches(string $purpose, array $records, bool $allowLegacy6CharSuffix): array
    {
        $purposeUpper = \strtoupper($purpose);
        if ($purposeUpper === '') {
            return [];
        }

        $matches = [];

        // 1. Exakte Suche nach dem vollständigen Code oder dem vollständigen 6-/8-stelligen ShortCode
        foreach ($records as $fullCode => $rec) {
            $shortCode = (string) $rec['shortCode'];
            if ($shortCode === '') {
                continue;
            }

            $fullQuoted = \preg_quote($fullCode, '/');
            $shortQuoted = \preg_quote($shortCode, '/');

            if (\preg_match('/(?<![A-Z0-9])' . $fullQuoted . '(?![A-Z0-9])/', $purposeUpper)) {
                $matches[$fullCode] = [
                    'extracted' => $fullCode,
                    'method' => 'PRIO 1: Exakter Voll-Code',
                ];
                continue;
            }

            if (!\preg_match('/(?<![A-Z0-9])(?:EFG[\s\-_]*)?' . $shortQuoted . '(?![A-Z0-9])/', $purposeUpper)) {
                continue;
            }

            $matches[$fullCode] = [
                'extracted' => $shortCode,
                'method' => 'PRIO 1: Exakter Code (' . \strlen($shortCode) . '-stellig)',
            ];
        }

        // 2. Temporärer 6-Stellen-Suffix-Fix für 8-stellige Codes (falls in Config aktiviert)
        if ($allowLegacy6CharSuffix) {
            foreach ($records as $fullCode => $rec) {
                if (isset($matches[$fullCode])) {
                    continue;
                }

                $shortCode = (string) $rec['shortCode'];
                if (\strlen($shortCode) !== 8) {
                    continue;
                }

                $suffix6 = \substr($shortCode, -6);
                $suffixQuoted = \preg_quote($suffix6, '/');

                if (!\preg_match('/(?<![A-Z0-9])(?:EFG[\s\-_]*)?' . $suffixQuoted . '(?![A-Z0-9])/', $purposeUpper)) {
                    continue;
                }

                $matches[$fullCode] = [
                    'extracted' => $suffix6,
                    'method' => "PRIO 1: 6-Stellen-Kurzcode '{$suffix6}' -> '{$shortCode}'",
                ];
            }
        }

        return $matches;
    }

    /**
     * PRIO 2: Sucht nach Kfz-Kennzeichen (nur von unbezahlten Genehmigungen) im Betreff.
     *
     * @param array<string, array<string, mixed>> $records
     *
     * @return array<string, string> Map: PermitCode => Erkanntes Kennzeichen
     */
    private function findPrio2LicensePlateMatches(string $purpose, array $records): array
    {
        $purposeNormalized = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', \mb_strtoupper($purpose, 'UTF-8'));
        if (\strlen($purposeNormalized) < 4) {
            return [];
        }

        $matches = [];
        foreach ($records as $fullCode => $rec) {
            if ($rec['status'] !== 'offen') {
                continue;
            }

            $plate = \trim((string) $rec['kennzeichen']);
            if (\in_array($plate, ['', '---', 'XXX-XX 9999', '[ANONYMISIERT]'], true)) {
                continue;
            }

            $plateNormalized = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', \mb_strtoupper($plate, 'UTF-8'));
            if (\strlen($plateNormalized) < 4 || $plateNormalized === 'XXXXX9999') {
                continue;
            }

            if (!\str_contains($purposeNormalized, $plateNormalized)) {
                continue;
            }

            $matches[$fullCode] = $plate;
        }

        return $matches;
    }

    /**
     * PRIO 3: Sucht nach vollständigem Namen (nur von Unbezahlten) im Betreff (Spalte 5)
     * und im Namen des Überweisenden (Spalte 12) - inkl. Umlaut-Normalisierung und Wortdreher-Erkennung.
     *
     * @param array<string, array<string, mixed>> $records
     *
     * @return array<string, string> Map: PermitCode => Fundstelle (z.B. "Max Mustermann [Betreff & Spalte 12]")
     */
    private function findPrio3NameMatches(string $purpose, string $senderName, array $records): array
    {
        $normPurpose = $this->normalizeTextForNameSearch($purpose);
        $normSender = $this->normalizeTextForNameSearch($senderName);

        if ($normPurpose === '' && $normSender === '') {
            return [];
        }

        $matches = [];
        foreach ($records as $fullCode => $rec) {
            if ($rec['status'] !== 'offen') {
                continue;
            }

            $ownerName = \trim((string) $rec['name']);
            $tokens = $this->extractNameTokens($ownerName);

            // Für eine sichere Erkennung des "vollständigen Namens" müssen mind. Vor- und Nachname vorhanden sein
            if (\count($tokens) < 2) {
                continue;
            }

            $inPurpose = $normPurpose !== '' && $this->containsAllNameTokens($normPurpose, $tokens);
            $inSender = $normSender !== '' && $this->containsAllNameTokens($normSender, $tokens);

            if (!$inPurpose && !$inSender) {
                continue;
            }

            $sourceLabel = match (true) {
                $inPurpose && $inSender => 'Betreff & Auftraggeber Spalte 12',
                $inPurpose => 'Betreff Spalte 5',
                default => 'Auftraggeber Spalte 12',
            };

            $matches[$fullCode] = "{$ownerName} [{$sourceLabel}]";
        }

        return $matches;
    }

    /**
     * PRIO 4: Sucht nach Parzellen-Angaben im Betreff (z.B. Parzelle 661, Parzelle 0661, Parzelle 0061, Parzelle 61).
     *
     * @param array<string, array<string, mixed>> $records
     *
     * @return array<string, string> Map: PermitCode => Formatierte Parzelle
     */
    private function findPrio4PlotMatches(string $purpose, array $records): array
    {
        if (\preg_match_all('/\b(?:PARZELLE|PARZ|PZ|GARTEN)\.?\s*(?:NR\.?\s*)?0*([1-9][0-9]{0,3})\b/iu', $purpose, $m) < 1) {
            return [];
        }

        $foundPlots = \array_unique(\array_map(intval(...), $m[1]));
        $matches = [];

        foreach ($records as $fullCode => $rec) {
            if ($rec['status'] !== 'offen') {
                continue;
            }

            $plotInt = (int) $rec['parzelle'];
            if ($plotInt <= 0 || !\in_array($plotInt, $foundPlots, true)) {
                continue;
            }

            $matches[$fullCode] = 'Parzelle ' . $rec['plotFormatted'];
        }

        return $matches;
    }

    /**
     * Baut einen strukturierten, angereicherten To-Do-Listeneintrag inklusive aller
     * relevanten Vergleichs-Permits (offen, bezahlt, storniert) zur Betrugs- und Dublettenerkennung.
     *
     * @param string[] $matchedCodes
     * @param array<string, array{extracted: string, method: string}> $extractedMap
     * @param array<string, array<string, mixed>> $allRecords
     *
     * @return array<string, mixed>
     */
    private function buildTodoItem(
        string $id,
        string $date,
        float $amount,
        string $currency,
        string $purpose,
        string $senderName,
        string $type,
        string $reasonTitle,
        string $reasonHint,
        array $matchedCodes,
        array $extractedMap,
        array $allRecords,
    ): array {
        $targetPlots = [];
        $targetNames = [];
        $targetPlates = [];
        $expectedSum = 0.0;
        $extractedPairs = [];

        foreach ($matchedCodes as $code) {
            if (!isset($allRecords[$code])) {
                continue;
            }
            $rec = $allRecords[$code];
            $expectedSum += (float) $rec['preis'];

            if ((int) $rec['parzelle'] > 0) {
                $targetPlots[(int) $rec['parzelle']] = true;
            }

            $normName = $this->normalizeTextForNameSearch((string) $rec['name']);
            if (!\in_array($normName, ['', 'anonymer nutzer', 'anonymisiert'], true)) {
                $targetNames[$normName] = true;
            }

            $normPlate = (string) \preg_replace('/[^A-Z0-9]/', '', \strtoupper((string) $rec['kennzeichen']));
            if (\strlen($normPlate) >= 4 && $normPlate !== 'XXXXX9999') {
                $targetPlates[$normPlate] = true;
            }

            $extractedStr = $extractedMap[$code]['extracted'] ?? '';
            $extractedPairs[] = [
                'extracted' => $extractedStr,
                'dbCode' => $code,
                'shortCode' => (string) $rec['shortCode'],
            ];
        }

        // Suche alle passenden Permits (Offen, Bezahlt, Storniert) zu Parzelle, Name oder Kennzeichen für den Betrugs-Check
        $relatedPermits = [];
        foreach ($allRecords as $candCode => $cand) {
            $isDirectMatch = \in_array($candCode, $matchedCodes, true);
            $samePlot = (int) $cand['parzelle'] > 0 && isset($targetPlots[(int) $cand['parzelle']]);
            $candNormName = $this->normalizeTextForNameSearch((string) $cand['name']);
            $sameName = $candNormName !== '' && isset($targetNames[$candNormName]);
            $candNormPlate = (string) \preg_replace('/[^A-Z0-9]/', '', \strtoupper((string) $cand['kennzeichen']));
            $samePlate = \strlen($candNormPlate) >= 4 && isset($targetPlates[$candNormPlate]);

            if (!$isDirectMatch && !$samePlot && !$sameName && !$samePlate) {
                continue;
            }

            $relationReasons = [];
            if ($isDirectMatch) {
                $relationReasons[] = 'Direkt-Treffer';
            } else {
                if ($samePlot) {
                    $relationReasons[] = 'Gleiche Parzelle';
                }
                if ($sameName) {
                    $relationReasons[] = 'Gleicher Name';
                }
                if ($samePlate) {
                    $relationReasons[] = 'Gleiches Kfz';
                }
            }

            $relatedPermits[] = [
                'code' => (string) $cand['code'],
                'shortCode' => (string) $cand['shortCode'],
                'extractedInSubject' => $extractedMap[$candCode]['extracted'] ?? null,
                'isDirectMatch' => $isDirectMatch,
                'relationLabel' => \implode(', ', $relationReasons),
                'name' => (string) $cand['name'],
                'plotFormatted' => (string) $cand['plotFormatted'],
                'kennzeichen' => (string) $cand['kennzeichen'],
                'typ' => (string) $cand['typ'],
                'preis' => (float) $cand['preis'],
                'status' => (string) $cand['status'],
                'vonFormatted' => (string) $cand['vonFormatted'],
                'bisFormatted' => (string) $cand['bisFormatted'],
                'erstelltFormatted' => (string) $cand['erstelltFormatted'],
                'bezahltAmFormatted' => $cand['bezahltAmFormatted'],
                'isSuspended' => (bool) $cand['isSuspended'],
            ];
        }

        // Sortiere: 1. Direkt-Treffer zuerst, 2. Offene zuerst, 3. nach Code
        \usort($relatedPermits, static function (array $a, array $b): int {
            if ($a['isDirectMatch'] !== $b['isDirectMatch']) {
                return $a['isDirectMatch'] ? -1 : 1;
            }
            if (($a['status'] === 'offen') !== ($b['status'] === 'offen')) {
                return $a['status'] === 'offen' ? -1 : 1;
            }

            return $b['erstelltFormatted'] <=> $a['erstelltFormatted'];
        });

        return [
            'id' => $id,
            'date' => $date,
            'amount' => $amount,
            'expectedAmount' => $expectedSum,
            'currency' => $currency,
            'purpose' => $purpose,
            'senderName' => $senderName,
            'type' => $type,
            'reasonTitle' => $reasonTitle,
            'reasonHint' => $reasonHint,
            'codes' => $matchedCodes,
            'extractedPairs' => $extractedPairs,
            'relatedPermits' => $relatedPermits,
        ];
    }

    private function normalizeTextForNameSearch(string $input): string
    {
        $lower = \mb_strtolower(\trim($input), 'UTF-8');
        if ($lower === '') {
            return '';
        }

        $transliterated = \str_replace(
            ['ä', 'ö', 'ü', 'ß', 'é', 'è', 'ê', 'á', 'à', 'â'],
            ['ae', 'oe', 'ue', 'ss', 'e', 'e', 'e', 'a', 'a', 'a'],
            $lower,
        );

        $cleaned = (string) \preg_replace('/[^a-z0-9]+/', ' ', $transliterated);

        return \trim((string) \preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * @return string[]
     */
    private function extractNameTokens(string $fullName): array
    {
        $normalized = $this->normalizeTextForNameSearch($fullName);
        if (\in_array($normalized, ['', 'anonymisiert', 'unbekannt'], true)) {
            return [];
        }

        $parts = \explode(' ', $normalized);
        $tokens = [];

        foreach ($parts as $part) {
            // Ignoriere akademische Titel oder 1-Buchstaben-Initialen
            if (\strlen($part) < 2 || \in_array($part, ['dr', 'prof', 'herr', 'frau', 'fam', 'familie', 'dipl', 'ing'], true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return \array_values(\array_unique($tokens));
    }

    /**
     * @param string[] $nameTokens
     */
    private function containsAllNameTokens(string $normalizedHaystack, array $nameTokens): bool
    {
        foreach ($nameTokens as $token) {
            if (!\preg_match('/\b' . \preg_quote($token, '/') . '\b/', $normalizedHaystack)) {
                return false;
            }
        }

        return true;
    }

    private function parseAmount(string $rawAmount): float
    {
        $cleaned = \preg_replace('/[^0-9,.\-]/', '', $rawAmount) ?? '0';
        if (\str_contains($cleaned, ',') && \str_contains($cleaned, '.')) {
            $cleaned = \str_replace('.', '', $cleaned);
            $cleaned = \str_replace(',', '.', $cleaned);
        } elseif (\str_contains($cleaned, ',')) {
            $cleaned = \str_replace(',', '.', $cleaned);
        }

        return (float) $cleaned;
    }

    private function parseDate(string $rawDate): string
    {
        $trimmed = \trim($rawDate);
        foreach (['d.m.y', 'd.m.Y', 'Y-m-d'] as $fmt) {
            $dateObj = DateTimeImmutable::createFromFormat($fmt, $trimmed);
            if ($dateObj !== false) {
                return $dateObj->format('d.m.Y');
            }
        }

        return $trimmed;
    }
}
