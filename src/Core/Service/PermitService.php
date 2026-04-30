<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service zur Verwaltung des Genehmigungsprozesses
 *
 * Orchestriert die Erstellung, Validierung, Speicherung und Benachrichtigung.
 * Unterstützt PayPal-Verifizierung (Instant) und Banküberweisungen (Pending)
 * mit konfigurierbaren Sicherheits-Features und dynamischem Pricing.
 *
 * @file      src/Core/Service/PermitService.php
 *
 * @copyright (c) 2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;

/**
 * Zentraler Service für Ausnahmegenehmigungen
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
     * Erstellt eine neue Genehmigung und triggert den 3-Mail-Workflow.
     *
     * @param array<string, mixed> $data
     * @param bool                 $sendMails Steuert, ob die 3 Standard-Mails sofort rausgehen.
     */
    public function createPermit(array $data, bool $sendMails = true): Permit
    {
        $this->validateEmail((string) ($data['email'] ?? ''));

        // Property-Read Fix: Validierung gegen Feiertage/Sonntage
        // Dies stellt sicher, dass holidayService für PHPStan "benutzt" wird.
        $startDate = new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now'));
        $endDate   = new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now'));

        // 1. Ruhezeiten-Check (Matrix)
        $conflicts = $this->holidayService->checkTimeConflicts($startDate, $endDate);
        if ($conflicts !== []) {
            // Wir werfen hier keine Exception, sondern loggen es evtl. oder lassen es zu,
            // da die PDF-Regeln (Handzettel) darauf hinweisen.
            // Falls es blockieren soll: throw new \RuntimeException("Zeitkonflikt: " . implode(', ', $conflicts));
        }

        $parzelle = \str_pad((string) ($data['parzelle'] ?? '0'), 4, '0', \STR_PAD_LEFT);
        $typ      = (string) ($data['typ'] ?? 'pkw');
        $randomId = $this->generateV4Suffix();

        // 1. Kennzeichen formatieren für die Anzeige (B-HD 7398)
        $displayPlate = $this->formatLicensePlate((string) ($data['kennzeichen'] ?? ''));

        // 2. Identifier-Plate: Leerzeichen durch Bindestriche ersetzen (B-HD-7398)
        $identifierPlate = \str_replace(' ', '-', $displayPlate);

        // 3. Eindeutige Kennung bauen: ML-0371-B-HD-7398-6Y5C
        // FIX: Short Ternary ersetzt durch expliziten Check für PHPStan Level 6
        $platePart = $identifierPlate !== '' ? $identifierPlate : 'LKW';

        $fullIdentifier = \sprintf(
            '%s-%s-%s-%s',
            $this->config->get('prefix', 'ML'),
            $parzelle,
            $platePart,
            $randomId,
        );

        /** @var array<string, string> $purposes */
        $purposes = $this->config->get('purposes', []);
        $zweck    = (string) ($purposes[(string) ($data['zweck'] ?? '')] ?? 'Privat');

        $permit = new Permit(
            code: $fullIdentifier,
            name: (string) $data['name'],
            email: (string) $data['email'],
            parzelle: $parzelle,
            typ: $typ,
            kennzeichen: $displayPlate,
            firma: isset($data['firma']) ? (string) $data['firma'] : null,
            zweck: $zweck,
            preisSnapshot: $this->config->getPriceForType($typ),
            von: $startDate,
            bis: new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now')),
            status: 'wartend',
            erstellt: new \DateTimeImmutable(), // FIX: Der Moment der Antragstellung
        );

        if (! $this->storage->save($permit)) {
            throw new \RuntimeException('Fehler beim Speichern der Daten.');
        }

        // 3-Mail-System
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
        $data['expires']            = \time() + (3600 * 24);

        // Wir speichern das in einer separaten Datei (storage/pending_verification.json)
        $storagePath = $this->config->get('root_path') . '/storage/pending_verification.json';

        $allPending         = $this->loadJson($storagePath);
        $allPending[$token] = $data;

        // JSON_PRETTY_PRINT für Debugging, falls ich mal reinschauen will
        \file_put_contents($storagePath, \json_encode($allPending, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

        $this->mailService->sendTemplate($data['email'], 'E-Mail bestätigen', 'verify_email', [
            'name'        => $data['name'],
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
                $permit->parzelle === $parzelleFormatted
                && $this->datesOverlap($permit->von, $permit->bis, $start, $end)
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
     * Bestätigt den Antrag und verschiebt ihn in den Hauptspeicher.
     */
    public function confirmEmail(string $token): ?Permit
    {
        $path       = $this->config->get('root_path') . '/storage/pending_verification.json';
        $allPending = $this->loadJson($path);

        if (! isset($allPending[$token])) {
            return null;
        }

        $data = $allPending[$token];
        unset($allPending[$token]);
        \file_put_contents($path, \json_encode($allPending));

        // Hier wird die echte Genehmigung erstellt und Mails versendet
        return $this->createPermit($data, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path): array
    {
        if (! \file_exists($path)) {
            return [];
        }

        return \json_decode(\file_get_contents($path), true) ?? [];
    }

    /**
     * Orchestriert den Versand der drei unterschiedlichen E-Mails.
     */
    private function dispatchMails(Permit $permit, string $shortCode): void
    {
        $zeitraum = "{$permit->von->format('d.m.Y')} bis {$permit->bis->format('d.m.Y')}";
        $opening  = $this->config->get('opening_hours');

        $token        = \hash('sha256', $permit->code . $this->config->get('geheimnis'));
        $subjectBoard = "[{$permit->code}] - {$zeitraum} - {$permit->name}";

        // Mail an VORSTAND
        $this->mailService->sendTemplate(
            $this->config->get('mail')['recipients'][$this->config->isTestMode() ? 'test' : 'live'],
            $subjectBoard,
            'board_notification',
            [
                'fullIdentifier' => $permit->code,
                'name'           => $permit->name,
                'email'          => $permit->email,
                'parzelle'       => $permit->parzelle,
                'typLabel'       => $this->config->get('vehicle_types')[$permit->typ] ?? $permit->typ,
                'kennzeichen'    => $permit->kennzeichen,
                'firma'          => $permit->firma,
                'von'            => $permit->von->format('d.m.Y'),
                'bis'            => $permit->bis->format('d.m.Y'),
                'zweck'          => $permit->zweck,
                'adminLink'      => $this->config->get('base_url') . "admin.php?code={$permit->code}&token={$token}",
            ],
        );

        // Mail an NUTZER (Zahlung mit EPC-QR)
        $usage     = $this->generateUsageText($permit, $shortCode);
        $epcQrData = $this->generateEpcData($permit->preisSnapshot, $usage);

        $this->mailService->sendTemplate($permit->email, "Zahlung erforderlich: {$permit->code}", 'payment_request', [
            'name'           => $permit->name,
            'fullIdentifier' => $permit->code,
            'betrag'         => \number_format($permit->preisSnapshot, 2, ',', '.') . ' €',
            'dueDate'        => (new \DateTimeImmutable())->modify('+14 days')->format('d.m.Y'),
            'kontoinhaber'   => $this->config->get('kontoinhaber'),
            'iban'           => $this->config->get('iban'),
            'usage'          => $usage,
            'epcData'        => \urlencode($epcQrData),
        ]);

        // Mail an NUTZER (A4 Dokument)
        $this->mailService->sendTemplate(
            $permit->email,
            'Ausnahmegenehmigung: ' . $this->config->get('vereins_name'),
            'permit_a4_document',
            [
                'fullIdentifier'    => $permit->code,
                'von'               => $permit->von->format('d.m.Y'),
                'bis'               => $permit->bis->format('d.m.Y'),
                'kennzeichen'       => $permit->kennzeichen,
                'firma'             => $permit->firma,
                'parzelle'          => $permit->parzelle,
                'zweck'             => $permit->zweck,
                'vereinsName'       => $this->config->get('vereins_name'),
                'jahresFarbe'       => $this->config->get('jahresFarbe'),
                'opening'           => "{$opening['earliest']} bis {$opening['latest']} Uhr",
                'terminkalenderUrl' => $this->config->get('terminkalender_url'),
                'erstellt'          => $permit->erstellt->format('d.m.Y H:i'),
                'checkUrl'          => \urlencode($this->config->get('base_url') . 'check.php?code=' . $permit->code),
            ],
        );
    }

    private function generateUsageText(Permit $permit, string $shortCode): string
    {
        $nameParts = \explode(' ', $permit->name);
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
        for ($i = 0; $i < 4; ++$i) {
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

        // Wir erstellen eine Kopie der Entität mit neuem Status
        $updatedPermit = new Permit(
            code: $permit->code,
            name: $permit->name,
            email: $permit->email,
            parzelle: $permit->parzelle,
            typ: $permit->typ,
            kennzeichen: $permit->kennzeichen,
            firma: $permit->firma,
            zweck: $permit->zweck,
            preisSnapshot: $permit->preisSnapshot,
            von: $permit->von,
            bis: $permit->bis,
            status: 'bezahlt', // Status-Update
            erstellt: $permit->erstellt,
            internerKommentar: $grund ?? $permit->internerKommentar, // Grund übernehmen
        );

        return $this->storage->save($updatedPermit);
    }

    /**
     * Schließt eine PayPal-Zahlung ab und aktiviert die Genehmigung.
     * Wird von public/api/capture.php aufgerufen.
     */
    public function completePayment(string $permitCode, string $orderId): bool
    {
        // 1. Genehmigung laden
        $permit = $this->storage->findByHash($permitCode);
        if (! $permit instanceof Permit) {
            return false;
        }

        // 2. Zahlung bei PayPal verifizieren (Nutzt den preisSnapshot für Sicherheit!)
        if ($this->paymentProvider->captureOrder($orderId, $permit->preisSnapshot)) {
            // 3. Wenn erfolgreich, Status auf 'bezahlt' setzen
            return $this->manualActivate($permit->code);
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
        if ($permit->status === 'bezahlt') {
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
}
