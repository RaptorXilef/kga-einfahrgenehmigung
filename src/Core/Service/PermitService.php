<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service zur Verwaltung des Genehmigungsprozesses.
 *
 * Orchestriert die Erstellung, Validierung, Speicherung und Benachrichtigung.
 * Unterstützt PayPal-Verifizierung (Instant) und Banküberweisungen (Pending)
 * mit konfigurierbaren Sicherheits-Features und dynamischem Pricing.
 *
 * @file      src/Core/Service/PermitService.php
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;

/**
 * Zentraler Service für Ausnahmegenehmigungen.
 */
final readonly class PermitService
{
    public function __construct(
        private StorageInterface $storage,
        private MailServiceInterface $mailService,
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private PaymentProviderInterface $paymentProvider,
        private VoucherService $voucherService,
    ) {
    }

    /**
     * Erstellt eine neue Genehmigung basierend auf Vorlagen. v0.14.0
     *
     * @param array<string, mixed> $data
     * @param bool                 $sendMails Steuert den sofortigen Mailversand.
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
        $endDate = $startDate->modify('+' . $template['days'] . ' days');
        if ($template['days'] === 'custom') {
            $endDate = new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now'));
        }

        // 2. Preis bestimmen (Template-Preis oder Admin-Override)
        $typ   = (string) ($data['typ'] ?? 'pkw');
        $preis = isset($data['manual_price'])
            ? (float) $data['manual_price']
            : (float) ($template['prices'][$typ] ?? 0.0);

        // Code-Generierung
        do {
            $randomId = $this->generateV4Suffix();

            // 1. Kennzeichen formatieren für die Anzeige (B-HD 7398)
            $displayPlate = $this->formatLicensePlate((string) ($data['kennzeichen'] ?? ''));

            // 2. Identifier-Plate: Leerzeichen durch Bindestriche ersetzen (B-HD-7398)
            $identifierPlate = \str_replace(' ', '-', $displayPlate);

            // 3. Eindeutige Kennung bauen: ML-0371-B-HD-7398-6Y5C
            $platePart = $identifierPlate !== '' ? $identifierPlate : 'LKW';

            $fullIdentifier = \sprintf(
                '%s-%s-%s-%s',
                $this->config->get('prefix', 'ML'),
                \str_pad((string) ($data['parzelle'] ?? '0'), 4, '0', \STR_PAD_LEFT),
                $platePart,
                $randomId,
            );
            // Wir prüfen, ob der Code bereits existiert (Storage oder Warteräume)
        } while ($this->storage->findByHash($fullIdentifier) instanceof Permit);

        /** @var array<string, string> $purposes */
        $purposes = (array) $this->config->get('purposes', []);
        $zweck    = (string) ($purposes[(string) ($data['zweck'] ?? '')] ?? 'Privat');

        // Value Objects-Instanziierung
        $permit = new Permit(
            code: $fullIdentifier,
            templateKey: $tKey, // WICHTIG: Speichert die Art der Genehmigung für später
            owner: new Owner(
                (string) $data['name'],
                (string) $data['email'],
                \str_pad((string) $data['parzelle'], 4, '0', \STR_PAD_LEFT),
            ),
            vehicle: new Vehicle($typ, $displayPlate, $data['firma'] ?? null),
            validity: new Validity($startDate, $endDate, $preis, $zweck),
            status: new Status((string) ($data['status'] ?? 'wartend')),
            erstellt: new \DateTimeImmutable(),
            internerKommentar: $data['internerKommentar'] ?? null,
        );

        if (! $this->storage->save($permit)) {
            throw new \RuntimeException('Speicherfehler.');
        }

        if ($sendMails) {
            $this->dispatchMails($permit, $randomId);
        }

        return $permit;
    }

    /**
     * Erstellt einen temporären Antrag, der erst bestätigt werden muss.
     *
     * @param array<string, mixed> $data
     */
    public function createPendingVerification(array $data): string
    {
        // 2. Kollisionsprüfung (Gleichzeitige Buchung der selben Parzelle)
        $this->validateNoCollisions(
            (string) ($data['parzelle'] ?? ''),
            new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now')),
            new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now')),
        );

        $token                      = \bin2hex(\random_bytes(32));
        $data['verification_token'] = $token;
        // Timeout aus Config laden (Standard 24h)
        $hours           = (int) $this->config->get('hours_pending_verify', 24);
        $data['expires'] = \time() + (3600 * $hours);

        // Wir speichern das in einer separaten Datei (storage/pending_verification.json)
        $storagePath        = $this->config->get('root_path') . '/storage/pending_verification.json';
        $allPending         = $this->loadJson($storagePath);
        $allPending[$token] = $data;

        $this->saveJson($storagePath, $allPending);

        $this->mailService->sendTemplate((string) $data['email'], 'E-Mail bestätigen', 'verify_email', [
            'name'        => (string) $data['name'],
            'verifyUrl'   => $this->config->getBaseUrl() . 'verify.php?token=' . $token,
            'vereinsName' => $this->config->get('vereins_name'),
        ]);

        return $token;
    }

    /**
     * Prüft, ob für eine Parzelle bereits Genehmigungen im Zeitraum vorliegen.
     * Wir prüfen bestätigte Genehmigungen UND offene Verifizierungen.
     */
    private function validateNoCollisions(string $parzelle, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $parzelleFormatted = \str_pad($parzelle, 4, '0', \STR_PAD_LEFT);

        // 1. Check im Hauptspeicher (Storage)
        foreach ($this->storage->getAll() as $permit) {
            if (
                $permit->owner->parzelle === $parzelleFormatted
                && $this->datesOverlap($permit->validity->von, $permit->validity->bis, $start, $end)
            ) {
                throw new \RuntimeException(
                    "Kollision: Für Parzelle {$parzelle} existiert bereits eine Genehmigung vom " .
                        $permit->von->format('d.m.Y') . ' bis ' . $permit->bis->format('d.m.Y') . '.',
                );
            }
        }

        // 2. Check in den ausstehenden E-Mail-Bestätigungen (Pending)
        $path       = $this->config->get('root_path') . '/storage/pending_verification.json';
        $allPending = $this->loadJson($path);

        foreach ($allPending as $pending) {
            $pStart = new \DateTimeImmutable((string) ($pending['datum_von'] ?? 'now'));
            $pEnd   = new \DateTimeImmutable((string) ($pending['datum_bis'] ?? 'now'));
            $pPlot  = \str_pad((string) ($pending['parzelle'] ?? ''), 4, '0', \STR_PAD_LEFT);

            // Nur prüfen, wenn die ausstehende Anfrage noch nicht abgelaufen ist
            if (
                $pPlot === $parzelleFormatted
                && (int) ($pending['expires'] ?? 0) > \time()
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
     * Die mathematische Formel für Zeit-Überschneidungen.
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
     * Schritt 2: Verschiebt von unbestätigt (24h) nach verifiziert (48h).
     *
     * @return array<string, mixed>|null
     */
    public function confirmEmail(string $token): ?array
    {
        $pendingPath  = $this->config->get('root_path') . '/storage/pending_verification.json';
        $verifiedPath = $this->config->get('root_path') . '/storage/verified_pending.json';

        $allPending = $this->loadJson($pendingPath);
        if (! isset($allPending[$token])) {
            // Falls der User den Link nochmal klickt, schauen wir, ob er schon in verified_pending liegt
            $allVerified = $this->loadJson($verifiedPath);
            /** @var array<string, mixed>|null $res */
            $res = $allVerified[$token] ?? null;

            return $res;
        }

        $data = (array) $allPending[$token];
        unset($allPending[$token]);
        $this->saveJson($pendingPath, $allPending);

        // In Warteraum 2 (48h) schieben
        $hours               = (int) $this->config->get('hours_pending_finalize', 48);
        $data['verified_at'] = \time();
        $data['expires']     = \time() + (3600 * $hours);

        $allVerified         = $this->loadJson($verifiedPath);
        $allVerified[$token] = $data;
        $this->saveJson($verifiedPath, $allVerified);

        // Sofort-Check: Falls ein Gutschein dabei war, direkt finalisieren
        $voucherCode = \trim((string) ($data['voucher'] ?? ''));
        if ($voucherCode !== '') {
            $voucher = $this->voucherService->useVoucher($voucherCode, $data);
            if ($voucher !== null) { // PHPStan Fix: Expliziter Check
                return ['finalised' => $this->finaliseRequest($token, 'bezahlt', 'Gutschein: ' . $voucherCode)];
            }
        }

        return $data;
    }

    /**
     * Schritt 3: Der eigentliche Umzug in die Datenbank und Mail-Versand.
     */
    public function finaliseRequest(string $token, string $status = 'wartend', ?string $kommentar = null): Permit
    {
        $verifiedPath = $this->config->get('root_path') . '/storage/verified_pending.json';
        $allVerified  = $this->loadJson($verifiedPath);

        if (! isset($allVerified[$token])) {
            throw new \RuntimeException('Antragssitzung abgelaufen oder bereits abgeschlossen.');
        }

        $data                      = (array) $allVerified[$token];
        $data['status']            = $status;
        $data['internerKommentar'] = $kommentar;

        // Echte Permit erstellen
        $permit = $this->createPermit($data, true);

        // Aus Warteraum löschen
        unset($allVerified[$token]);
        $this->saveJson($verifiedPath, $allVerified);

        return $permit;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function saveJson(string $path, array $data): void
    {
        \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path): array
    {
        if (! \file_exists($path)) {
            return [];
        }

        $data = (array) \json_decode((string) \file_get_contents($path), true) ?? [];

        // --- AUTO-CLEANUP für Pending-Files ---
        if (\str_contains($path, 'pending_verification')) {
            $now           = \time();
            $originalCount = \count($data);

            // Entferne alle abgelaufenen Einträge
            $data = \array_filter(
                $data,
                fn (array $item): bool => isset($item['expires']) && (int) $item['expires'] > $now,
            );

            // Wenn etwas gelöscht wurde, Datei direkt bereinigen
            if (\count($data) !== $originalCount) {
                $this->saveJson($path, $data);
            }
        }

        return $data;
    }

    /**
     * Orchestriert den Versand der unterschiedlichen E-Mails.
     */
    private function dispatchMails(Permit $permit, string $shortCode): void
    {
        // Zugriff via $permit->validity->von statt $permit->von
        $zeitraum  = "{$permit->validity->von->format('d.m.Y')} bis {$permit->validity->bis->format('d.m.Y')}";
        $geheimnis = (string) $this->config->get('geheimnis', '');
        $token     = \hash('sha256', $permit->code . $geheimnis);
        $opening   = $this->config->get('opening_hours');

        // Mail an VORSTAND
        $this->mailService->sendTemplate(
            $this->config->get('mail')['recipients'][$this->config->isTestMode() ? 'test' : 'live'],
            "[{$permit->code}] - {$zeitraum} - {$permit->owner->name}",
            'board_notification',
            [
                'fullIdentifier' => $permit->code,
                'name'           => $permit->owner->name,
                'email'          => $permit->owner->email,
                'parzelle'       => $permit->owner->parzelle,
                'typLabel'       => $this->config->get('vehicle_types')[$permit->vehicle->typ] ?? $permit->vehicle->typ,
                'kennzeichen'    => $permit->vehicle->kennzeichen,
                'firma'          => $permit->vehicle->firma ?? '',
                'von'            => $permit->validity->von->format('d.m.Y'),
                'bis'            => $permit->validity->bis->format('d.m.Y'),
                'zweck'          => $permit->validity->zweck,
                'adminLink'      => $this->config->getBaseUrl() . "admin.php?code={$permit->code}&token={$token}",
            ],
        );

        // 2. Mail an NUTZER (Zahlung mit EPC-QR) - NUR WENN NOCH NICHT BEZAHLT
        if ($permit->status->current !== 'bezahlt') {
            $usage     = $this->generateUsageText($permit, $shortCode);
            $epcQrData = $this->generateEpcData($permit->validity->preisSnapshot, $usage);

            $this->mailService->sendTemplate(
                $permit->owner->email,
                "Zahlung erforderlich: {$permit->code}",
                'payment_request',
                [
                    'name'           => $permit->owner->name,
                    'fullIdentifier' => $permit->code,
                    'betrag'         => \number_format($permit->validity->preisSnapshot, 2, ',', '.') . ' €',
                    'dueDate'        => (new \DateTimeImmutable())->modify('+14 days')->format('d.m.Y'),
                    'kontoinhaber'   => $this->config->get('kontoinhaber'),
                    'iban'           => $this->config->get('iban'),
                    'usage'          => $usage,
                    'epcData'        => \urlencode($epcQrData),
                ],
            );
        }

        // Mail an NUTZER (A4 Dokument)
        $this->mailService->sendTemplate(
            $permit->owner->email,
            'Ausnahmegenehmigung: ' . $this->config->get('vereins_name'),
            'permit_a4_document',
            [
                'fullIdentifier'    => $permit->code,
                'von'               => $permit->validity->von,
                'bis'               => $permit->validity->bis,
                'kennzeichen'       => $permit->vehicle->kennzeichen,
                'firma'             => $permit->vehicle->firma ?? '',
                'parzelle'          => $permit->owner->parzelle,
                'zweck'             => $permit->validity->zweck,
                'templateKey'       => $permit->templateKey,
                'vereinsName'       => $this->config->get('vereins_name'),
                'jahresFarbe'       => $this->config->get('jahresFarbe'),
                'opening'           => "{$opening['earliest']} bis {$opening['latest']} Uhr",
                'terminkalenderUrl' => $this->config->get('terminkalender_url'),
                'erstellt'          => $permit->erstellt->format('d.m.Y H:i'),
                'checkUrl'          => \urlencode($this->config->getBaseUrl() . 'check.php?code=' . $permit->code),
                'config'            => $this->config,
            ],
        );
    }

    private function generateUsageText(Permit $permit, string $shortCode): string
    {
        $nameParts = \explode(' ', $permit->owner->name);
        $vorname   = $nameParts[0] ?? 'Unbekannt';
        $nachname  = $nameParts[\count($nameParts) - 1] ?? 'Unbekannt';

        return "EFG-{$nachname}-{$vorname}-{$shortCode}";
    }

    private function generateEpcData(float $amount, string $reference): string
    {
        // SEPA EPC-QR-Code (BezahlCode) Standard
        return "BCD\n001\n1\nSCT\n" .
            $this->config->get('bic') . "\n" .
            $this->config->get('kontoinhaber') . "\n" .
            $this->config->get('iban') . "\n" .
            'EUR' . \number_format($amount, 2, '.', '') . "\n" .
            "\n" . // Purpose Code leer
            "\n" . // Structured Reference leer
            $reference;
    }

    /**
     * Formatiert deutsche Kennzeichen (z.B. BHD7398 -> B-HD 7398).
     */
    private function formatLicensePlate(string $plate): string
    {
        $val = \strtoupper((string) \preg_replace('/[^A-Z0-9]/', '', $plate));
        if (\strlen($val) <= 3) {
            return $val;
        }

        // Berlin-Priorität (Regex-Strings korrekt in PHP-Syntax)
        if (\preg_match('/^(B)([A-Z]{1,2})(\d{1,4})$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        if (\preg_match('/^([A-Z]{1,3})([A-Z]{1,2})(\d{1,4})$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        return (string) \preg_replace('/^([A-Z]{1,3})(\d{1,4})$/', '$1 $2', $val);
    }

    /**
     * Generiert den neuen v4 Code: [PREFIX]-[YY]-[0000]-[RAND]
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

    private function validateEmail(string $email): void
    {
        if (! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Die eingegebene E-Mail-Adresse ist ungültig.');
        }
    }

    /**
     * Aktiviert eine Genehmigung manuell (Zahlungseingang bestätigt).
     */
    public function manualActivate(string $code, ?string $grund = null): bool
    {
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->templateKey,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status(
                'bezahlt', // Status-Update
                $permit->status->isSuspended,
                $permit->status->suspensionReason,
            ),
            $permit->erstellt,
            $grund ?? $permit->internerKommentar, // Grund übernehmen
        );

        return $this->storage->save($updated);
    }

    /**
     * Schließt eine PayPal-Zahlung ab und finalisiert den Antrag.
     * NEU in v0.12.0: Nutzt das Token, um im Warteraum 2 zu suchen.
     */
    public function completePayment(string $token, string $orderId): bool
    {
        $verifiedPath = $this->config->get('root_path') . '/storage/verified_pending.json';
        $allVerified  = $this->loadJson($verifiedPath);

        if (! isset($allVerified[$token])) {
            return false;
        }

        $data = (array) $allVerified[$token];

        // Zahlung bei PayPal verifizieren
        if ($this->paymentProvider->captureOrder($orderId, (float) $data['preisSnapshot'])) {
            // Wenn erfolgreich -> In die echte Datenbank verschieben
            $this->finaliseRequest($token, 'bezahlt', 'Bezahlt via PayPal');

            return true;
        }

        return false;
    }

    /**
     * Ermittelt den Überfälligkeits-Status.
     * 0 = Pünktlich
     * 1 = Zahlungsziel überschritten (Gelbe Warnung)
     * 2 = Benachrichtigungs-Zeitraum überschritten (Roter Alarm für Buchhaltung)
     */
    public function getOverdueLevel(Permit $permit): int
    {
        if ($permit->status->current === 'bezahlt') {
            return 0;
        }

        $now        = new \DateTimeImmutable();
        $dueDays    = (int) $this->config->get('payment_due_days', 14);
        $notifyDays = (int) $this->config->get('payment_due_days_notify', 2);

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

    public function getVoucherService(): VoucherService
    {
        return $this->voucherService;
    }

    /**
     * Lädt einen verifizierten, aber noch nicht finalisierten Antrag.
     *
     * @return array<string, mixed>|null
     */
    public function getVerifiedRequest(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $path = $this->config->get('root_path') . '/storage/verified_pending.json';
        $all  = $this->loadJson($path);

        return (array) ($all[$token] ?? null) ?: null;
    }

    /**
     * Findet alle finalisierten Genehmigungen einer E-Mail-Adresse.
     *
     * @return Permit[]
     */
    public function getHistoryByEmail(string $email): array
    {
        $all = $this->storage->getAll();

        return \array_filter(
            $all,
            fn (Permit $permit): bool => \strtolower($permit->owner->email) === \strtolower($email),
        );
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Prüft, welche Quartale (1-4) vom Zeitraum der Genehmigung berührt werden.
     *
     * @return array<int>
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
     * Prüft und führt die Jahres-Archivierung durch v0.16.0
     */
    public function checkAndArchive(): void
    {
        $archiveDeadline = (string) $this->config->get('archive_deadline', '02-01'); // Standard 1. Feb
        if (\date('m-d') < $archiveDeadline) {
            return;
        }

        $lastYear = (int) \date('Y') - 1;
        $mainPath = $this->config->get('root_path') . '/storage/daten.json';
        $all      = $this->loadJson($mainPath);

        $toArchive  = [];
        $stayInMain = [];

        foreach ($all as $code => $data) {
            $year = (int) \substr((string) $data['erstellt'], 0, 4);
            if ($year <= $lastYear) {
                $toArchive[$code] = $data;

                continue;
            }
            $stayInMain[$code] = $data;
        }

        if ($toArchive === []) {
            return;
        }

        $yearPath = $this->config->get('root_path') . "/storage/daten_{$lastYear}.json";
        // Bestehendes Archiv laden oder neu erstellen
        $existingYear = \file_exists(
            $yearPath,
        ) ? (array) \json_decode((string) \file_get_contents($yearPath), true) : [];
        $newYearData = \array_merge($existingYear, $toArchive);

        $this->saveJson($yearPath, $newYearData);
        $this->saveJson($mainPath, $stayInMain);
    }

    /**
     * Sperrt oder entsperrt eine Genehmigung
     */
    public function toggleSuspension(string $code, bool $status, ?string $reason = null): bool
    {
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->templateKey,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status(
                $permit->status->current,
                $status,
                $reason,
            ),
            $permit->erstellt,
            $permit->internerKommentar,
        );

        return $this->storage->save($updated);
    }

    /**
     * Hilfsmethode für Controller, um Rohdaten in eine Entität zu wandeln
     *
     * @param array<string, mixed> $data
     */
    public function arrayToEntity(array $data): Permit
    {
        return $this->storage->mapToEntity($data);
    }
}
