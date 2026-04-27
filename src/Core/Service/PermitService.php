<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service zur Verwaltung des Genehmigungsprozesses (v0.9.0).
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

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Infrastructure\Config\Config;

/**
 * Zentraler Service für Ausnahmegenehmigungen
 */
final readonly class PermitService
{
    public function __construct(
        private StorageInterface $storage,
        private MailServiceInterface $mailService,
        private Config $config,
        private HolidayService $holidayService,
        private PaymentProviderInterface $paymentProvider,
    ) {
    }

    /**
     * Erstellt eine neue Genehmigung und triggert den 3-Mail-Workflow.
     *
     * @param bool $sendMails Steuert, ob die 3 Standard-Mails sofort rausgehen.
     */
    public function createPermit(array $data, bool $sendMails = true): Permit
    {
        $this->validateEmail($data['email'] ?? '');

        $parzelle = \str_pad((string) ($data['parzelle'] ?? '0'), 4, '0', \STR_PAD_LEFT);
        $typ      = $data['typ'] ?? 'pkw';
        $randomId = $this->generateV4Suffix();

        // 1. Kennzeichen formatieren für die Anzeige (B-HD 7398)
        $displayPlate = $this->formatLicensePlate((string) ($data['kennzeichen'] ?? ''));

        // 2. Identifier-Plate: Leerzeichen durch Bindestriche ersetzen (B-HD-7398)
        $identifierPlate = \str_replace(' ', '-', $displayPlate);

        // 3. Eindeutige Kennung bauen: ML-0371-B-HD-7398-6Y5C
        $fullIdentifier = \sprintf(
            '%s-%s-%s-%s',
            $this->config->get('prefix', 'ML'),
            $parzelle,
            $identifierPlate ?: 'LKW',
            $randomId,
        );

        $permit = new Permit(
            code: $fullIdentifier,
            name: (string) $data['name'],
            email: (string) $data['email'],
            parzelle: $parzelle,
            typ: $typ,
            kennzeichen: $displayPlate,
            firma: $data['firma'] ?? null,
            zweck: (string) ($this->config->get('purposes')[$data['zweck']] ?? 'Privat'),
            preisSnapshot: $this->config->getPriceForType($typ),
            von: new \DateTimeImmutable($data['datum_von']),
            bis: new \DateTimeImmutable($data['datum_bis']),
            status: 'wartend',
        );

        if (! $this->storage->save($permit)) {
            throw new \RuntimeException('Kritischer Fehler beim Speichern der Daten.');
        }

        // 3-Mail-System
        // FIX: Hier nutzen wir jetzt den Parameter $sendMails
        if ($sendMails) {
            $this->dispatchMails($permit, $randomId);
        }

        return $permit;

    }

    /**
     * Erstellt einen temporären Antrag, der erst bestätigt werden muss.
     */
    public function createPendingVerification(array $data): string
    {
        $token                      = \bin2hex(\random_bytes(32));
        $data['verification_token'] = $token;
        $data['expires']            = \time() + (3600 * 24); // 24h gültig

        // Wir speichern das in einer separaten Datei
        $storagePath = $this->config->get('root_path') . '/storage/pending_verification.json';

        $allPending         = $this->loadJson($storagePath);
        $allPending[$token] = $data;

        \file_put_contents($storagePath, \json_encode($allPending, \JSON_UNESCAPED_UNICODE));

        // Verifizierungs-Mail senden
        $verifyUrl = $this->config->getBaseUrl() . 'verify.php?token=' . $token;
        $this->mailService->sendTemplate($data['email'], 'E-Mail bestätigen: Ausnahmegenehmigung', 'verify_email', [
            'name'      => $data['name'],
            'verifyUrl' => $verifyUrl,
        ]);

        return $token;
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

    private function loadJson(string $path): array
    {
        if (! \file_exists($path)) {
            return [];
        }

        return \json_decode(\file_get_contents($path), true) ?: [];
    }

    /**
     * Orchestriert den Versand der drei unterschiedlichen E-Mails.
     */
    private function dispatchMails(Permit $p, string $shortCode): void
    {
        $zeitraum = "{$p->von->format('d.m.Y')} bis {$p->bis->format('d.m.Y')}";
        $opening  = $this->config->get('opening_hours');

        $token        = \hash('sha256', $p->code . $this->config->get('geheimnis'));
        $subjectBoard = "[{$p->code}] - {$zeitraum} - {$p->name}";

        // Mail an VORSTAND
        $this->mailService->sendTemplate($this->config->get('mail')['recipients'][$this->config->isTestMode() ? 'test' : 'live'], $subjectBoard, 'board_notification', [
            'fullIdentifier' => $p->code,
            'name'           => $p->name,
            'email'          => $p->email,
            'parzelle'       => $p->parzelle,
            'typLabel'       => $this->config->get('vehicle_types')[$p->typ] ?? $p->typ,
            'kennzeichen'    => $p->kennzeichen,
            'firma'          => $p->firma,
            'von'            => $p->von->format('d.m.Y'),
            'bis'            => $p->bis->format('d.m.Y'),
            'zweck'          => $p->zweck,
            'adminLink'      => $this->config->get('base_url') . "admin.php?code={$p->code}&token={$token}",
        ]);

        // Mail an NUTZER (Zahlung mit EPC-QR)
        $usage     = $this->generateUsageText($p, $shortCode);
        $epcQrData = $this->generateEpcData($p->preisSnapshot, $usage);

        $this->mailService->sendTemplate($p->email, "Zahlung erforderlich: {$p->code}", 'payment_request', [
            'name'           => $p->name,
            'fullIdentifier' => $p->code,
            'betrag'         => \number_format($p->preisSnapshot, 2, ',', '.') . ' €',
            'dueDate'        => (new \DateTimeImmutable())->modify('+14 days')->format('d.m.Y'),
            'kontoinhaber'   => $this->config->get('kontoinhaber'),
            'iban'           => $this->config->get('iban'),
            'usage'          => $usage,
            'epcData'        => \urlencode($epcQrData),
        ]);

        // Mail an NUTZER (A4 Dokument)
        $this->mailService->sendTemplate($p->email, 'Ausnahmegenehmigung: ' . $this->config->get('vereins_name'), 'permit_a4_document', [
            'fullIdentifier'    => $p->code,
            'von'               => $p->von->format('d.m.Y'),
            'bis'               => $p->bis->format('d.m.Y'),
            'kennzeichen'       => $p->kennzeichen,
            'firma'             => $p->firma,
            'parzelle'          => $p->parzelle,
            'zweck'             => $p->zweck,
            'vereinsName'       => $this->config->get('vereins_name'),
            'jahresFarbe'       => $this->config->get('jahresFarbe'),
            'opening'           => "{$opening['earliest']} bis {$opening['latest']} Uhr",
            'terminkalenderUrl' => $this->config->get('terminkalender_url'),
            'erstellt'          => $p->erstellt?->format('d.m.Y H:i') ?? 'Sofort',
            'checkUrl'          => \urlencode($this->config->get('base_url') . 'check.php?code=' . $p->code),
        ]);
    }

    private function generateUsageText(Permit $p, string $shortCode): string
    {
        $nameParts = \explode(' ', $p->name);
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
        if (\preg_match('/^(B)([A-Z]{1,2})(\d{1,4})$/', $val, $m)) {
            return "{$m[1]}-{$m[2]} {$m[3]}";
        }
        if (\preg_match('/^([A-Z]{1,3})([A-Z]{1,2})(\d{1,4})$/', $val, $m)) {
            return "{$m[1]}-{$m[2]} {$m[3]}";
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
    public function manualActivate(string $code): bool
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
}
