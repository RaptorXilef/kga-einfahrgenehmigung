<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;
use App\Infrastructure\Storage\JsonHelper;

/**
 * Haupt-Service für die Erstellung, Prüfung und Verwaltung von Einfahrtsgenehmigungen.
 * Handhabt den Workflow von der initialen Anfrage bis zur finalen Genehmigung.
 *
 * Steuert Kollisionsprüfungen, Validierungsketten, Kennzeichen-Formatierung, E-Mail-Verifikationen,
 * Rechnungsstellungen, PayPal-Zahlungsabschlüsse und automatisierte Archivierungsprozesse.
 * Kontext: Der zentrale Business-Logik-Kern (Domain Service) des gesamten Genehmigungssystems.
 *
 * Service zur Verwaltung des Genehmigungsprozesses. / Zentraler Service für Ausnahmegenehmigungen.
 *
 * Path: src/Core/Service/PermitService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitService
{
    public function __construct(
        private BankQrGenerator $bankQrGenerator,
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private LicensePlateFormatter $plateFormatter,
        private MailServiceInterface $mailService,
        private PaymentProviderInterface $paymentProvider,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private StorageInterface $storage,
        private VerificationRepositoryInterface $verificationRepository,
        private VoucherService $voucherService,
    ) {
    }

    // --- Application Entry Channels (Double-Opt-In Workflow) ---

    /**
     * Schritt 1: Formular absenden, Mail-Code erzeugen
     *
     * Erstellt eine neue Genehmigungsanfrage (Warteraum) und sendet die Verifizierungs-E-Mail.
     *
     * @param array $data Die Formulardaten des Antrags.
     *
     * @return string Das generierte Verifizierungs-Token.
     *
     * @throws \RuntimeException Bei Datumskollisionen mit bestehenden oder ausstehenden Anträgen.
     */
    public function createPendingVerification(array $data): string
    {
        // 2. Kollisionsprüfung (Gleichzeitige Buchung der selben Parzelle)
        $this->validateNoCollisions(
            (string) ($data['parzelle'] ?? ''),
            new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now')),
            new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now')),
        );

        $tKey          = $data['template_key'] ?? 'std_7';
        $templates     = (array) $this->config->get('permit_templates', []);
        $template      = $templates[$tKey] ?? $templates['std_7'];
        $vehicleTypes  = (array) $this->config->get('vehicle_types', []);
        $defaultType   = $vehicleTypes === [] ? 'pkw' : \array_key_first($vehicleTypes);
        $typ           = $data['typ'] ?? $defaultType;
        $data['preis'] = (float) (
            $template['prices'][$typ] ?? ($template['prices'][$defaultType] ?? 0.0)
        );
        $token                      = \bin2hex(\random_bytes(32));
        $shortCode                  = \strtoupper(\substr(\bin2hex(\random_bytes(4)), 0, 6));
        $data['verification_token'] = $token;
        $data['verification_code']  = $shortCode;
        $hours                      = (int) $this->config->get('hours_pending_verify', 24);
        $data['expires']            = \date('Y-m-d H:i:s', APP_REQUEST_TIME + (3600 * $hours));
        $allPending                 = $this->verificationRepository->loadPending();
        $allPending[$token]         = $data;
        $this->verificationRepository->savePending($allPending);
        $this->mailService->sendTemplate(
            (string) $data['email'],
            'E-Mail bestätigen',
            'verify_email',
            [
                'name'        => (string) $data['name'],
                'verifyUrl'   => $this->config->getBaseUrl() . 'verify.php?token=' . $token,
                'code'        => $shortCode,
                'vereinsName' => $this->config->get('vereins_name'),
            ],
        );

        return $token;
    }

    /**
     * Schritt 2: Mail-Code bestätigen, Gutscheine anrechnen
     *
     * Bestätigt eine E-Mail-Verifizierung mittels Token oder Short-Code.
     * Zieht ggf. Gutscheincodes ab.
     *
     * @param string $input Das 64-stellige Token oder der 6-stellige Short-Code.
     *
     * @return array|null Das Array mit den finalisierten oder aktualisierten Daten, oder null bei Fehlschlag.
     */
    public function confirmEmail(string $input): ?array
    {
        $allPending   = $this->verificationRepository->loadPending();
        $input        = \strtoupper(\trim($input));
        $matchedToken = null;

        // 1. Suche nach Lang-Token ODER Kurz-Code
        foreach ($allPending as $t => $d) {
            if (\strtoupper($t) === $input || \strtoupper((string) ($d['verification_code'] ?? '')) === $input) {
                $matchedToken = $t;

                break;
            }
        }

        // 2. Double-Click Check: Falls nicht mehr in 'pending', schaue in 'verified'
        if ($matchedToken === null) {
            $allVerified = $this->verificationRepository->loadVerified();
            foreach ($allVerified as $t => $d) {
                if (\strtoupper($t) === $input || \strtoupper((string) ($d['verification_code'] ?? '')) === $input) {
                    return $d;
                }
            }

            return null;
        }

        // 2. Daten aus 'pending' extrahieren und dort löschen
        $token = $matchedToken;
        $data  = (array) $allPending[$token];
        unset($allPending[$token]);
        $this->verificationRepository->savePending($allPending);

        // 3. Neue Ablaufzeit für Warteraum 2 setzen (z.B. 48h für Zahlung)
        $hours               = (int) $this->config->get('hours_pending_finalize', 48);
        $data['verified_at'] = APP_REQUEST_TIME_STR;
        $data['expires']     = \date('Y-m-d H:i:s', APP_REQUEST_TIME + (3600 * $hours));

        // 4. GUTSCHEIN-LOGIK (ERWEITERT)
        // Gutscheincode zwingend in Großbuchstaben umwandeln, da er im Repository
        // so indexiert ist und Kleinschreibungen bei der Formularübermittlung sonst fehlschlagen.
        $voucherCode = \strtoupper(\trim((string) ($data['voucher'] ?? '')));
        if ($voucherCode !== '') {
            $voucher = $this->voucherService->useVoucher($voucherCode, $data);

            if ($voucher !== null) {
                // Berechne den Preis nach Abzug des Rabatts
                $finalPrice = $this->calculateDiscountedPrice((float) $data['preis'], $voucher);

                // Fall A: Gutschein deckt alles (0,00 €)
                if ($finalPrice <= 0.0) {
                    $data['preis']  = 0.0;
                    $data['status'] = 'bezahlt';

                    // Vor dem Finalisieren MUSS der Datensatz im neuen Speicher abgelegt werden!
                    $allVerified         = $this->verificationRepository->loadVerified();
                    $allVerified[$token] = $data;
                    $this->verificationRepository->saveVerified($allVerified);

                    // Wir müssen es hier nicht in verified_pending speichern,
                    // sondern können es sofort finalisieren.
                    return ['finalised' => $this->finaliseRequest(
                        $token,
                        'bezahlt',
                        'Gutschein (Voll-Rabatt): ' . $voucherCode,
                    )];
                }

                // Fall B: Restbetrag bleibt offen (Teil-Rabatt)
                $data['preis']           = $finalPrice;
                $data['voucher_applied'] = $voucherCode;
                $data['voucher_details'] = ['type' => $voucher['type'], 'value' => $voucher['value']];
            }
        }

        // 5. In Warteraum 2 (verified_pending) speichern
        $allVerified         = $this->verificationRepository->loadVerified();
        $allVerified[$token] = $data;
        $this->verificationRepository->saveVerified($allVerified);

        $data['actual_token'] = $token; // Wir legen den echten Key dazu

        return $data;
    }

    /**
     * Schritt 3: Vom Warteraum in echten Speicher überführen
     *
     * Finalisiert einen ausstehenden Antrag und überführt ihn in den regulären Speicher.
     *
     * @param string      $token     Das Verifizierungs-Token.
     * @param string      $status    Der Status der neuen Genehmigung (z.B. 'offen' oder 'bezahlt').
     * @param string|null $kommentar Ein optionaler interner Kommentar für das System.
     *
     * @return Permit Die erstellte Genehmigungs-Entität.
     *
     * @throws \RuntimeException Wenn das Token ungültig oder der Antrag bereits abgeschlossen ist.
     */
    public function finaliseRequest(string $token, string $status = 'offen', ?string $kommentar = null): Permit
    {
        // TODO ggf. in Storage auslagern, da Dateizugriff (prüfen)
        // TODO Pfad und Dateiname in config/storage.php auslagern
        // Atomarer Prozess-Lock, um TOCTOU-Datenverlust bei parallelen Checkouts zu verhindern!
        $lockFile = \rtrim((string) $this->config->get('root_path'), '/\\') . '/storage/logs/checkout.lock';
        $lockFp   = @\fopen($lockFile, 'c');
        if ($lockFp) {
            \flock($lockFp, \LOCK_EX);
        }

        try {
            $allVerified = $this->verificationRepository->loadVerified();

            if (! isset($allVerified[$token])) {
                throw new \RuntimeException('Antragssitzung abgelaufen oder bereits abgeschlossen.');
            }

            $data                       = (array) $allVerified[$token];
            $data['status']             = $status;
            $data['interner_kommentar'] = $kommentar;
            $permit                     = $this->createPermit($data, true);

            // Aus Warteraum löschen
            unset($allVerified[$token]);

            $this->verificationRepository->saveVerified($allVerified);

            return $permit;
        } finally {
            // Sperre garantiert wieder aufheben, selbst wenn createPermit einen Fehler wirft
            if ($lockFp) {
                \flock($lockFp, \LOCK_UN);
                \fclose($lockFp);
            }
        }
    }

    /**
     * Alternativer Schritt 3: PayPal-Zahlung einziehen & finalisieren
     *
     * Schließt eine Online-Zahlung (z.B. PayPal) ab und finalisiert den Antrag.
     *
     * @param string $token   Das Verifizierungs-Token.
     * @param string $orderId Die ID der Zahlung/Bestellung des Payment-Providers.
     *
     * @return bool True bei erfolgreicher Transaktion, sonst false.
     */
    public function completePayment(string $token, string $orderId): bool
    {
        $allVerified = $this->verificationRepository->loadVerified();

        if (! isset($allVerified[$token])) {
            return false;
        }

        $data = (array) $allVerified[$token];

        // Zahlung bei PayPal verifizieren
        if ($this->paymentProvider->captureOrder($orderId, (float) $data['preis'])) {
            // Wenn erfolgreich -> In die echte Datenbank verschieben
            $this->finaliseRequest($token, 'bezahlt', 'Bezahlt via PayPal');

            return true;
        }

        return false;
    }

    // --- Core Entity Factory ---

    /**
     * Wird von finaliseRequest aufgerufen, baut die Permit-Entität
     *
     * Erstellt eine neue Genehmigung basierend auf Vorlagen und versendet ggf. E-Mails..
     *
     * Fabrikmethode zur Generierung und direkten Speicherung einer voll-hydrierten Permit-Entität.
     * Berechnet Ablaufdaten, formatiert Kennzeichen, erzeugt eindeutige System-Identifikatoren,
     * zieht Tarife heran und stößt optionale Benachrichtigungs-Mails an den Nutzer und Vorstand an.
     *
     * @param array $data      Die genehmigungsrelevanten Daten.
     * @param bool  $sendMails Gibt an, ob Benachrichtigungs-E-Mails gesendet werden sollen.
     *
     * @return Permit Die neu erstellte Genehmigungs-Entität.
     *
     * @throws \RuntimeException Bei Speicherfehlern.
     */
    public function createPermit(array $data, bool $sendMails = true): Permit
    {
        $this->validateEmail((string) ($data['email'] ?? ''));

        // Vorlagen-Logik laden
        $tKey      = (string) ($data['template_key'] ?? 'std_7');
        $templates = (array) $this->config->get('permit_templates', []);
        $template  = (array) ($templates[$tKey] ?? $templates['std_7']);

        // 1. Zeiträume bestimmen
        $startDate = new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now'));

        // Automatische Berechnung der Tage aus der Vorlage
        // --- FIX: -1 Tag für Inklusiv-Zählung (z.B. 7 Tage = heute + 6) ---
        if ($template['days'] === 'custom') {
            $endDate = new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now'));
        } else {
            $daysToAdd = \max(0, (int) $template['days'] - 1);
            $endDate   = $startDate->modify('+' . $daysToAdd . ' days');
        }

        // 2. Preis bestimmen (Template-Preis oder Admin-Override) (pkw)
        $vehicleTypes = $this->config->get('vehicle_types', []);
        $defaultType  = empty($vehicleTypes) ? 'pkw' : \array_key_first($vehicleTypes);
        $typ          = (string) ($data['typ'] ?? $defaultType);
        $preis        = isset($data['manual_price'])
            ? (float) $data['manual_price']
            : (float) ($template['prices'][$typ] ?? 0.0);

        // Code-Generierung
        do {
            $randomId = $this->generateV4Suffix();

            // 1. Kennzeichen formatieren für die Anzeige (B-HD 7398)
            $displayPlate = $this->plateFormatter->format((string) ($data['kennzeichen'] ?? ''));

            // 2. Identifier-Plate: Leerzeichen durch Bindestriche ersetzen (B-HD-7398)
            $identifierPlate = \str_replace(' ', '-', $displayPlate);

            // 3. Eindeutige Kennung bauen: ML-0371-B-HD-7398-6Y5C
            // Wir nehmen den Typ-Key als Teil des Codes, falls kein Kennzeichen da ist (z.B. "ABWASSER")
            $platePart = $identifierPlate !== '' ? $identifierPlate : \strtoupper($typ);

            // Konfiguration prüfen und Code formatieren
            $useLongCode = (bool) $this->config->get('use_long_permit_code', false);

            if ($useLongCode) {
                $fullIdentifier = \sprintf(
                    '%s-%s-%s-%s',
                    $this->config->get('prefix', 'ML'),
                    \str_pad((string) ($data['parzelle'] ?? '0'), 4, '0', \STR_PAD_LEFT),
                    $platePart,
                    $randomId,
                );
            } else {
                $fullIdentifier = $randomId;
            }

            // Wir prüfen, ob der Code bereits existiert (Storage oder Warteräume)
            // Globale Prüfung über alle Archive hinweg
        } while (! $this->isCodeGloballyUnique($fullIdentifier));

        /** @var array<string, string> $purposes */
        $purposes = (array) $this->config->get('purposes', []);
        $zweck    = (string) ($purposes[(string) ($data['zweck'] ?? '')] ?? 'Privat');

        // Value Objects-Instanziierung
        $permit = new Permit(
            code: $fullIdentifier,
            template_key: $tKey, // WICHTIG: Speichert die Art der Genehmigung für später
            owner: new Owner(
                \strip_tags((string) $data['name']),
                (string) $data['email'],
                \str_pad((string) $data['parzelle'], 4, '0', \STR_PAD_LEFT),
            ),
            vehicle: new Vehicle(
                $typ,
                $displayPlate,
                isset($data['firma']) ? \strip_tags((string) $data['firma']) : null,
            ),
            validity: new Validity($startDate, $endDate, $preis, $zweck),
            status: new Status((string) ($data['status'] ?? 'offen')),
            erstellt: new \DateTimeImmutable(),
            interner_kommentar: $data['interner_kommentar'] ?? null,
            agreements: $data['agreements'] ?? [],
        );

        if (! $this->storage->save($permit)) {
            throw new \RuntimeException('Speicherfehler.');
        }

        if ($sendMails) {
            $this->dispatchMails($permit, $randomId);
        }

        return $permit;
    }

    // --- Administrative Actions ---

    /**
     * Manuelle Barzahlung im Admin-Bereich
     *
     * Markiert eine Genehmigung manuell als bezahlt.
     *
     * @param string      $code  Der Code der Genehmigung.
     * @param string|null $grund Optionaler Kommentar zum Vorgang.
     *
     * @return bool True bei Erfolg, false bei nicht gefundener Genehmigung.
     */
    public function manualActivate(string $code, ?string $grund = null): bool
    {
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->template_key,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status(
                'bezahlt', // Status-Update
                $permit->status->is_suspended,
                $permit->status->suspension_reason,
            ),
            $permit->erstellt,
            $grund ?? $permit->interner_kommentar, // Grund übernehmen
        );

        return $this->storage->save($updated);
    }

    /**
     * Genehmigung sperren / entsperren
     *
     * Setzt oder entfernt die Sperre einer Genehmigung.
     *
     * @param string      $code   Der Code der Genehmigung.
     * @param bool        $status True zum Sperren, False zum Entsperren.
     * @param string|null $reason Begründung für die Sperre.
     *
     * @return bool True wenn erfolgreich, false wenn nicht gefunden.
     */
    public function toggleSuspension(string $code, bool $status, ?string $reason = null): bool
    {
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->template_key,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status(
                $permit->status->current,
                $status,
                $reason,
            ),
            $permit->erstellt,
            $permit->interner_kommentar,
        );

        return $this->storage->save($updated);
    }

    // --- Live Real-Time Queries ---

    /**
     * Sucht und filtert für die AJAX-Echtzeitsuche
     *
     * Sucht, filtert und paginiert Genehmigungen (inkl. Archiv).
     *
     * @return array{items: array, total: int}
     */
    public function searchAndPaginate(string $query, string $tab, string $templateType, int $page, int $limit): array
    {
        // 1. Alle aktiven Genehmigungen laden (Hauptspeicher)
        $allActive = $this->storage->getAll();

        // 2. Archiv laden, falls explizit danach gesucht wird oder 'all' gewählt ist
        // HINWEIS: Bei sehr großen JSON-Archiven optimieren wir das später noch auf reine DB-Queries
        $archived = [];
        if (\in_array($tab, ['all', 'archive'], true)) {
            $arcCfg      = $this->config->get('storage_config')['permits_archive'];
            $archivePath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' .
                \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . ($arcCfg['file'] ?? 'permits_archive.json');

            if (\file_exists($archivePath)) {
                $rawArchive = JsonHelper::read($archivePath);
                foreach ($rawArchive as $item) {
                    $archived[] = $this->arrayToEntity($item);
                }
            }
        }

        // 3. Zusammenführen und filtern
        $combined   = \array_merge($allActive, $archived);
        $filtered   = [];
        $queryLower = \strtolower($query);
        $now        = new \DateTimeImmutable();

        $permitTemplates = $this->config->get('permit_templates', []);

        foreach ($combined as $permit) {
            // A. Template-Typ Filter
            if ($templateType !== 'all') {
                $tplType = $permitTemplates[$permit->template_key]['type'] ?? 'standard';
                if ($tplType !== $templateType) {
                    continue;
                }
            }

            // B. Tab-Filter (Aktiv, Abgelaufen, Archiv)
            $isArchived = $this->archiveRepository->isCodeInArchive($permit->code);
            $isExpired  = $permit->validity->bis < $now;

            if ($tab === 'active' && ($isArchived || $isExpired)) {
                continue;
            }
            if ($tab === 'expired' && (! $isExpired || $isArchived)) {
                continue;
            }
            if ($tab === 'archive' && ! $isArchived) {
                continue;
            }

            // C. Text-Suche (falls ein Suchbegriff existiert)
            if ($queryLower !== '') {
                $searchString = \strtolower(
                    $permit->code . ' ' .
                        $permit->owner->name . ' ' .
                        $permit->owner->email . ' ' .
                        $permit->vehicle->kennzeichen . ' ' .
                        $permit->owner->parzelle . ' ' .
                        $permit->validity->zweck,
                );

                if (! \str_contains($searchString, $queryLower)) {
                    continue;
                }
            }

            $filtered[] = $permit;
        }

        // 4. Sortieren (Neueste zuerst)
        \usort($filtered, fn ($a, $b) => $b->erstellt <=> $a->erstellt);

        // 5. Paginierung (Array zuschneiden)
        $total  = \count($filtered);
        $offset = ($page - 1) * $limit;
        $items  = \array_slice($filtered, $offset, $limit);

        // 6. Für die API als flache Arrays formatieren
        // [ ] Sortiert
        $formattedItems = \array_map(fn ($p) => [
            'code'         => $p->code,
            'name'         => $p->owner->name,
            'email'        => $p->owner->email,
            'parzelle'     => $p->owner->parzelle,
            'kennzeichen'  => $p->vehicle->kennzeichen,
            'zweck'        => $p->validity->zweck,
            'preis'        => $p->validity->preis,
            'status'       => $p->status->current,
            'erstellt'     => $p->erstellt->format('d.m.Y H:i'),
            'von'          => $p->validity->von->format('d.m.Y'),
            'bis'          => $p->validity->bis->format('d.m.Y'),
            'is_archived'  => $this->archiveRepository->isCodeInArchive($p->code),
            'template_key' => $p->template_key,
        ], $items);

        return [
            'items' => $formattedItems,
            'total' => $total,
        ];
    }

    /**
     * Sucht alle Genehmigungen für den Pächter-Verlauf
     *
     * Filtert alle aktiven Speicherdaten nach Genehmigungen einer spezifischen E-Mail-Adresse.
     *
     * @param string $email Die Such-E-Mail-Adresse.
     *
     * @return array<int, Permit> Liste passender Genehmigungen.
     */
    public function getHistoryByEmail(string $email): array
    {
        $all = $this->storage->getAll();

        return \array_filter(
            $all,
            fn (Permit $permit): bool => \strtolower($permit->owner->email) === \strtolower($email),
        );
    }

    /**
     * Holt offene Sitzungsdaten für den Checkout
     *
     * Ruft eine verifizierte, aber noch nicht bezahlte/abgeschlossene Antragssitzung ab.
     *
     * @param string $token Das Sitzungs-Token.
     *
     * @return array<string, mixed>|null Die Antragsdaten oder null.
     */
    public function getVerifiedRequest(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $all = $this->verificationRepository->loadVerified();

        return (array) ($all[$token] ?? null) ?: null;
    }

    /**
     * Berechnet Mahnstufe für die Finanzüberwachung
     *
     * Ermittelt die Eskalationsstufe für überfällige Zahlungen.
     *
     * Stufe 0: Innerhalb der Zahlungsfrist.
     * Stufe 1: Zahlungsfrist überschritten (Zahlungsverzug für Nutzer) (Gelbe Warnung).
     * Stufe 2: Kulanzfrist ebenfalls abgelaufen (Warnstufe für das Admin-Personal) (Roter Alarm für Buchhaltung).
     *
     * @param Permit $permit Die zu prüfende Genehmigung.
     *
     * @return int Die Eskalationsstufe (0, 1, 2).
     */
    public function getOverdueLevel(Permit $permit): int
    {
        if ($permit->status->current === 'bezahlt') {
            return 0;
        }

        $now                 = new \DateTimeImmutable();
        $dueDays             = (int) $this->config->get('payment_due_days', 14);
        $notifyDays          = (int) $this->config->get('payment_due_days_notify', 2);
        $userDeadline        = $permit->erstellt->modify("+{$dueDays} days");
        $staffAlertThreshold = $userDeadline->modify("+{$notifyDays} days");

        if ($now > $staffAlertThreshold) {
            return 2; // Stufe: Buchhaltung informieren
        }

        if ($now > $userDeadline) {
            return 1; // Stufe: Zahlungsziel überschritten
        }

        return 0;
    }

    // --- Automated Maintenance Tasks ---

    // TODO Methode ggf. aus PermitService weitgehend weg refactorieren
    /**
     * Cronjob-Aktion: Archivierung
     *
     * Verschiebt abgelaufene und abgeschlossene Genehmigungen ins Archiv.
     */
    public function autoArchiveExpiredPermits(int $graceDays = 0): int
    {
        $allPermits = $this->storage->getAll();
        $toArchive  = [];

        // Stichtag berechnen (Mitternacht)
        $cutoffDate = (new \DateTimeImmutable())->modify("-{$graceDays} days")->setTime(0, 0, 0);

        foreach ($allPermits as $permit) {
            // 1. Bedingung: Ist das "bis" Datum kleiner als unser Stichtag?
            if ($permit->validity->bis < $cutoffDate) {

                // 2. Bedingung: Ist der Status endgültig abgeschlossen? (bezahlt oder storniert)
                if (\in_array($permit->status->current, ['bezahlt', 'storniert'], true)) {
                    $toArchive[] = $this->entityToArray($permit);
                }
            }
        }

        if (! empty($toArchive)) {
            // Ins Archiv schreiben
            $this->archiveRepository->archivePermits(0, $toArchive);

            // TODO GGF. folgendes in andere passendere Klasse auslagern und hier nur aufrufen
            // OPTIMIERUNG: Unterscheidung zwischen JSON-Massenlöschung und SQL
            $storageEngine = $this->storage;
            if ($storageEngine instanceof \App\Infrastructure\Storage\JsonStorage) {
                // Bei JSON: Einmal im RAM bereinigen und mit einem einzigen I/O-Vorgang speichern
                $cfg  = $this->config->get('storage_config')['permits'];
                $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' .
                    \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];

                $fp = @\fopen($path, 'c+');
                if ($fp && \flock($fp, \LOCK_EX)) {
                    $stat = \fstat($fp);
                    $size = $stat['size'];
                    $raw  = $size > 0 ? \fread($fp, $size) : '';
                    $data = JsonHelper::decode((string) $raw);

                    foreach ($toArchive as $item) {
                        unset($data[$item['code']]);
                    }

                    \ftruncate($fp, 0);
                    \fseek($fp, 0);
                    $jsonStr = \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
                    if (\fwrite($fp, $jsonStr) === false) {
                        throw new \RuntimeException('Kritischer Schreibfehler beim Archivieren der abgelaufenen Genehmigungen.');
                    }
                    \fflush($fp);
                    \flock($fp, \LOCK_UN);
                    \fclose($fp);
                }
            } else {
                // Bei SQL: Normale Ausführung über Prepared Statements
                foreach ($toArchive as $item) {
                    $this->storage->delete($item['code']);
                }
            }
        }

        return \count($toArchive);
    }

    // TODO Methode ggf. weg refactorieren
    /**
     * Cronjob-Aktion: DSGVO-Löschung
     *
     * Trigger für die DSGVO-konforme Anonymisierung veralteter Archiv-Einträge.
     *
     * @param int $yearsThreshold Die Aufbewahrungsfrist in Jahren.
     *
     * @return int Anzahl der anonymisierten Datensätze.
     */
    public function anonymizeOldArchiveRecords(int $yearsThreshold = 10): int
    {
        return $this->archiveRepository->anonymizeOldRecords($yearsThreshold);
    }

    // --- Infrastruktur & Daten-Migration ---

    // TODO savePendingData Methode weg refactorieren (Siehe Notizen PermitService-Ref.txt)
    /**
     * Öffentliche Brücke für die Migration, um Warteraum-Daten zu speichern.
     *
     * Schreibt Rohdaten direkt in die internen, temporären Tabellen/Dateien des Registrierungsprozesses.
     *
     * @param string               $category Die Tabellen- oder Dateikategorie.
     * @param array<string, mixed> $data     Das Speicher-Array.
     */
    public function savePendingData(string $category, array $data, bool $forceSql = false): void
    {
        if ($category === 'pending_verification') {
            $this->verificationRepository->savePending($data, $forceSql);
        } else {
            $this->verificationRepository->saveVerified($data, $forceSql);
        }
    }

    // --- Internal Dispatchers & Core Generation (Private) ---

    /**
     * Verschickt Dokumente und Benachrichtigungen
     *
     * Versendet automatisierte E-Mail-Pakete nach der Erstellung einer Genehmigung.
     * Generiert EPC-konforme QR-Überweisungsdaten (SEPA-Stuzza), berechnet verschlüsselte Admin-Validierungslinks
     * und verschickt die PDF/A4-Dokumenten-E-Mail an den Nutzer sowie die Infomail an den Vereinsvorstand.
     *
     * @param Permit $permit    Das erstellte Genehmigungs-Objekt.
     * @param string $shortCode Der zufällige Suffix-Code für den Verwendungszweck.
     */
    private function dispatchMails(Permit $permit, string $shortCode): void
    {
        $zeitraum   = "{$permit->validity->von->format('d.m.Y')} bis {$permit->validity->bis->format('d.m.Y')}";
        $geheimnis  = (string) $this->config->get('geheimnis', '');
        $token      = \hash('sha256', $permit->code . $geheimnis);
        $opening    = $this->holidayService->getOpeningHoursTextForDateRange($permit->validity->von, $permit->validity->bis);
        $mailConfig = $this->config->getMailSettings();

        // Prüfung, ob die Vorstands-Benachrichtigung gesendet werden soll (Standard: true, falls nicht gesetzt)
        if (($mailConfig['send_board_notification'] ?? true) === true) {
            // --- 1. MAIL AN VORSTAND (Immer senden) ---
            // [x] Sortiert
            $this->mailService->sendTemplate(
                data: [
                    'adminLink'      => $this->config->getBaseUrl() . "check.php?code={$permit->code}&token={$token}",
                    'bis_formatted'  => $permit->validity->bis->format('d.m.Y'),
                    'email'          => $permit->owner->email ?: 'Keine angegeben',
                    'firma'          => $permit->vehicle->firma ?? '',
                    'fullIdentifier' => $permit->code,
                    'kennzeichen'    => $permit->vehicle->kennzeichen,
                    'name'           => $permit->owner->name,
                    'parzelle'       => $permit->owner->parzelle,
                    'preis'          => \number_format($permit->validity->preis, 2, ',', '.') . ' €',
                    'typLabel'       => (function ($typ, $config) {
                        $vConfigs = $config->get('vehicle_types', []);

                        return $vConfigs[$typ]['label'] ?? 'Fahrzeug: ' . \strtoupper($typ);
                    })($permit->vehicle->typ, $this->config), // (Sicher gegen gelöschte Fahrzeugtypen):
                    'vereinsName'   => $this->config->get('vereins_name'),
                    'von_formatted' => $permit->validity->von->format('d.m.Y'),
                    'zweck'         => $permit->validity->zweck,
                ],
                recipient: $mailConfig['recipients'][$this->config->isTestMode() ? 'test' : 'live'],
                subject: "[{$permit->code}] - {$zeitraum} - {$permit->owner->name}",
                template: 'board_notification',
            );
        }

        // --- MAIL AN NUTZER (Nur wenn E-Mail vorhanden ist) ---
        if (\in_array(\trim($permit->owner->email), ['', '0'], true)) {
            return;
        }

        // 2. ZAHLUNGSAUFFORDERUNG (Nur wenn noch nicht bezahlt)
        if ($permit->status->current !== 'bezahlt') {
            $usage     = $this->generateUsageText($permit, $shortCode);
            $epcQrData = $this->bankQrGenerator->generate($permit->validity->preis, $usage);

            // [ ] Sortiert
            $this->mailService->sendTemplate(
                $permit->owner->email,
                "Zahlung erforderlich: {$permit->code}",
                'payment_request',
                [
                    'name'           => $permit->owner->name,
                    'fullIdentifier' => $permit->code,
                    'betrag'         => \number_format($permit->validity->preis, 2, ',', '.') . ' €',
                    'dueDate'        => (new \DateTimeImmutable())->modify('+14 days')->format('d.m.Y'),
                    'kontoinhaber'   => $this->config->get('kontoinhaber'),
                    'iban'           => $this->config->get('iban'),
                    'usage'          => $usage,
                    'epcData'        => \urlencode($epcQrData),
                ],
            );
        }

        // 3. DAS A4 DOKUMENT (KEINE DUPLIKATE MEHR!)
        // [ ] Sortiert
        $this->mailService->sendTemplate(
            $permit->owner->email,
            'Ausnahmegenehmigung: ' . $this->config->get('vereins_name'),
            'permit_a4_document',
            [
                'fullIdentifier' => $permit->code,
                'von_formatted'  => $permit->validity->von->format('d.m.Y'),
                'bis_formatted'  => $permit->validity->bis->format('d.m.Y'),
                'kennzeichen'    => $permit->vehicle->kennzeichen,
                'firma'          => $permit->vehicle->firma ?? '',
                'parzelle'       => $permit->owner->parzelle,
                'zweck'          => $permit->validity->zweck,
                'template_key'   => $permit->template_key,
                'vereinsName'    => $this->config->get('vereins_name'),
                'jahresFarbe'    => $this->config->get('jahresFarbe'),
                'opening'        => $opening,
                'holidayNotice'  => $this->holidayService->getHolidaysInRangeText(
                    $permit->validity->von,
                    $permit->validity->bis,
                ),
                'terminkalenderUrl' => $this->config->get('terminkalender_url'),
                'erstellt'          => $permit->erstellt->format('d.m.Y H:i'),
                'checkUrl'          => \urlencode($this->config->getBaseUrl() . 'check.php?code=' . $permit->code),
            ],
        );
    }

    /**
     * Sperrt Doppelbuchungen derselben Parzelle
     *
     * Prüft, ob für eine Parzelle bereits Genehmigungen im Zeitraum vorliegen.
     * Wir prüfen bestätigte Genehmigungen UND offene Verifizierungen.
     *
     * Validiert Anträge auf Überschneidungsfreiheit pro Parzelle (Kollisionsschutz).
     * Blockiert Anträge, falls für die Parzelle im Zielzeitraum bereits eine aktive Genehmigung
     * existiert oder eine offene Double-Opt-In-Verifikation blockiert wird (Spam-/Double-Booking-Schutz).
     *
     * @param string             $parzelle Die Parzellennummer.
     * @param \DateTimeImmutable $start    Gewünschtes Startdatum.
     * @param \DateTimeImmutable $end      Gewünschtes Enddatum.
     *
     * @return void Wirft eine Exception bei Überschneidungskonflikten.
     */
    private function validateNoCollisions(string $parzelle, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $parzelleFormatted = \str_pad($parzelle, 4, '0', \STR_PAD_LEFT);

        // 1. Check im Hauptspeicher (Storage Interface)
        foreach ($this->storage->getAll() as $permit) {
            if (
                $permit->owner->parzelle === $parzelleFormatted
                && $this->datesOverlap($permit->validity->von, $permit->validity->bis, $start, $end)
            ) {
                throw new \RuntimeException(
                    "Kollision: Für Parzelle {$parzelle} existiert bereits eine Genehmigung vom " .
                        $permit->validity->von->format('d.m.Y') . ' bis ' .
                        $permit->validity->bis->format('d.m.Y') . '.',
                );
            }
        }

        // 2. Check in den ausstehenden E-Mail-Bestätigungen (Pending)
        $allPending = $this->verificationRepository->loadPending();
        $nowStr     = APP_REQUEST_TIME_STR;

        foreach ($allPending as $pending) {
            $pPlot   = \str_pad((string) ($pending['parzelle'] ?? ''), 4, '0', \STR_PAD_LEFT);
            $pStart  = new \DateTimeImmutable((string) ($pending['datum_von'] ?? 'now'));
            $pEnd    = new \DateTimeImmutable((string) ($pending['datum_bis'] ?? 'now'));
            $expires = $pending['expires'] ?? '';

            // Nur prüfen, wenn die ausstehende Anfrage noch nicht abgelaufen ist
            if (
                $pPlot === $parzelleFormatted
                && $expires > $nowStr
                && $this->datesOverlap($pStart, $pEnd, $start, $end)
            ) {
                throw new \RuntimeException(
                    "Hinweis: Für Parzelle {$parzelle} läuft bereits eine Anfrage für diesen Zeitraum. " .
                        'Bitte warten Sie 24h oder wählen Sie andere Daten.',
                );
            }
        }
    }

    /**
     * Mathematischer Abgleich für Kollision
     *
     * Prüft, ob zwei Datumsbereiche miteinander überlappen.
     */
    private function datesOverlap(
        \DateTimeImmutable $startA,
        \DateTimeImmutable $endA,
        \DateTimeImmutable $startB,
        \DateTimeImmutable $endB,
    ): bool {
        return $startA <= $endB && $endA >= $startB;
    }

    /**
     * Erzeugt krypto-sicheren 6-Stelligen Kurzcode
     *
     * Generiert einen kryptografisch sicheren, zufälligen 6-stelligen alphanumerischen Identifikations-Suffix.
     * Schließt zur Vermeidung von Lesefehlern verwechslungsanfällige Zeichen wie '0', '1', 'I' und 'O' strukturell aus.
     *
     * Generiert den neuen v4 Code: [PREFIX]-[YY]-[0000]-[RAND]
     *
     * @return string Der generierte Suffix (z.B. "XF7R9A").
     */
    private function generateV4Suffix(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $res   = '';
        // Von 4 auf 6 Zeichen erhöht für 1400 Parzellen / 10 Jahre Sicherheit
        for ($i = 0; $i < 6; ++$i) {
            $res .= $chars[\random_int(0, \strlen($chars) - 1)];
        }

        return $res;
    }

    /**
     * Baut den IBAN-Verwendungszweck zusammen
     *
     * Generiert den eindeutigen, standardisierten Verwendungszweck für Banküberweisungen.
     * Aufbau-Schema: EFG-[Nachname]-[Vorname]-[Kurzcode]
     *
     * @param Permit $permit    Die Genehmigung.
     * @param string $shortCode Der Suffix-Code.
     *
     * @return string Der bereinigte Verwendungszweck-String.
     */
    private function generateUsageText(Permit $permit, string $shortCode): string
    {
        $nameParts = \explode(' ', $permit->owner->name);
        $vorname   = $nameParts[0] ?? 'Unbekannt';
        $nachname  = $nameParts[\count($nameParts) - 1] ?? 'Unbekannt';

        return "EFG-{$nachname}-{$vorname}-{$shortCode}";
    }

    /**
     * Validiert E-Mail-Adressen formal über native PHP-Filter.
     *
     * @return void Wirft eine Exception bei ungültiger Syntax.
     */
    private function validateEmail(string $email): void
    {
        // Wenn das Feld leer ist, überspringen wir die Prüfung (da optional)
        if (\trim($email) === '') {
            return;
        }

        if (! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Die eingegebene E-Mail-Adresse ist ungültig.');
        }
    }

    /**
     * Überprüft die globale, systemweite Einzigartigkeit eines Genehmigungs-Codes.
     * Scannt hierzu das aktive Storage, die SQL-Archivtabellen sowie alle historischen
     * JSON-Jahresarchive auf der Festplatte.
     *
     * @param string $fullIdentifier Der zu prüfende Gesamt-Code.
     *
     * @return bool True, wenn der Code im gesamten System noch nie vergeben wurde.
     */
    private function isCodeGloballyUnique(string $fullIdentifier): bool
    {
        // 1. Check in Haupt-Datenbank (Storage Interface macht das automatisch passend)
        if ($this->storage->findByHash($fullIdentifier) instanceof Permit) {
            return false;
        }

        return ! $this->archiveRepository->isCodeInArchive($fullIdentifier);
    }

    // --- Data Hydration & Infrastructure Getters ---

    // TODO arrayToEntity Methode weg refactorieren (Siehe Notizen PermitService-Ref.txt)
    /**
     * Brückenmethode zur Hydrierung von assoziativen Speicher-Arrays in starke Permit-Entitäten.
     *
     * @param array<string, mixed> $data
     */
    public function arrayToEntity(array $data): Permit
    {
        return $this->storage->mapToEntity($data);
    }

    /**
     * Wandelt eine Permit-Entität in ein flaches Array für das Archiv um.
     */
    public function entityToArray(Permit $permit): array
    {
        // [ ] Sortiert
        return [
            'code'               => $permit->code,
            'template_key'       => $permit->template_key,
            'name'               => $permit->owner->name,
            'email'              => $permit->owner->email,
            'kennzeichen'        => $permit->vehicle->kennzeichen,
            'parzelle'           => $permit->owner->parzelle,
            'typ'                => $permit->vehicle->typ,
            'firma'              => $permit->vehicle->firma,
            'zweck'              => $permit->validity->zweck,
            'preis'              => $permit->validity->preis,
            'von'                => $permit->validity->von->format('Y-m-d'),
            'bis'                => $permit->validity->bis->format('Y-m-d'),
            'status'             => $permit->status->current,
            'erstellt'           => $permit->erstellt->format('Y-m-d H:i:s'),
            'interner_kommentar' => $permit->interner_kommentar,
            'is_anonymized'      => 0,
            'agreements'         => \is_array($permit->agreements) ? \json_encode(
                $permit->agreements,
                \JSON_UNESCAPED_UNICODE,
            ) : '{}',
        ];
    }

    /**
     * Berechnet die vom Gültigkeitszeitraum abgedeckten Kalenderquartale (z.B. für Finanzstatistiken) (1-4).
     *
     * @param Permit $permit Die Genehmigung.
     *
     * @return array<int, int> Array der Quartalsnummern (z.B. [2, 3] bei Laufzeit von Mai bis August).
     */
    public function getCoveredQuarters(Permit $permit): array
    {
        $startQ = (int) \ceil((int) $permit->validity->von->format('n') / 3);
        $endQ   = (int) \ceil((int) $permit->validity->bis->format('n') / 3);

        // Wenn es über ein Jahr hinausgeht, müssten wir mehr tun,
        // aber für Dauerkarten innerhalb eines Kalenderjahres reicht:
        return \range($startQ, $endQ);
    }

    /**
     * Berechnet den rabattierten Preis basierend auf den Gutscheindaten.
     *
     * @param float $originalPrice Der ursprüngliche Preis.
     * @param array $voucher       Die Gutscheindaten.
     *
     * @return float Der berechnete Endpreis (kann nicht < 0 fallen).
     */
    public function calculateDiscountedPrice(float $originalPrice, array $voucher): float
    {
        $type  = $voucher['type'] ?? 'free';
        $value = (float) ($voucher['value'] ?? 0.0);
        // [x] Sortiert
        $newPrice = match ($type) {
            'fixed'   => $value,
            'free'    => 0.0,
            'percent' => $originalPrice * (1 - ($value / 100)),
            default   => $originalPrice,
        };

        return \max(0.0, $newPrice);
    }

    // TODO get Methode weg refactorieren (Siehe Notizen PermitService-Ref.txt)
    /**
     * Gibt die aktive Datenhaltungs-Engine (Storage) zurück.
     */
    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    // TODO get Methode weg refactorieren (Siehe Notizen PermitService-Ref.txt)
    /**
     * Gibt den Mail-Warteschlangen-Dienst zurück.
     */
    public function getMailService(): MailServiceInterface
    {
        return $this->mailService;
    }

    // TODO get Methode weg refactorieren (Siehe Notizen PermitService-Ref.txt)
    /**
     * Gibt die Instanz des Archiv-Repositories zurück.
     */
    public function getArchiveRepository(): PermitArchiveRepositoryInterface
    {
        return $this->archiveRepository;
    }

    // TODO get Methode weg refactorieren (Siehe Notizen PermitService-Ref.txt)
    /**
     * Liefert den injizierten Gutschein-Service.
     */
    public function getVoucherService(): VoucherService
    {
        return $this->voucherService;
    }
}
