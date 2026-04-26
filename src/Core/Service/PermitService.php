<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service zur Verwaltung des Genehmigungsprozesses (v0.5.0).
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
 *
 * @since     0.1.0
 * @since     0.5.0 - feat(core): Eindeutige Kennung und EPC-QR-Code Integration.
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Infrastructure\Config\Config;
use DateTimeImmutable;
use RuntimeException;

/**
 * Zentraler Service für Ausnahmegenehmigungen (v0.5.0).
 */
final readonly class PermitService
{
    public function __construct(
        private StorageInterface $storage,
        private MailServiceInterface $mailService,
        private Config $config,
        private HolidayService $holidayService,
    ) {
    }

    /**
     * Erstellt eine neue Genehmigung und triggert den 3-Mail-Workflow.
     */
    public function createPermit(array $data): Permit
    {
        $this->validateEmail($data['email'] ?? '');

        $parzelle = \str_pad((string) ($data['parzelle'] ?? '0'), 4, '0', STR_PAD_LEFT);
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
            von: new DateTimeImmutable($data['datum_von']),
            bis: new DateTimeImmutable($data['datum_bis']),
            status: 'wartend',
        );

        if (! $this->storage->save($permit)) {
            throw new RuntimeException('Kritischer Fehler beim Speichern der Daten.');
        }

        // 3-Mail-System
        $this->dispatchMails($permit, $randomId);

        return $permit;
    }

    /**
     * Orchestriert den Versand der drei unterschiedlichen E-Mails.
     */
    private function dispatchMails(Permit $p, string $shortCode): void
    {
        $zeitraum = "{$p->von->format('d.m.Y')} bis {$p->bis->format('d.m.Y')}";
        $opening  = $this->config->get('opening_hours');

        // 1. Mail an VORSTAND
        $token        = \hash('sha256', $p->code . $this->config->get('geheimnis'));
        $subjectBoard = "[{$p->code}] - {$zeitraum} - {$p->name}";

        $this->mailService->sendTemplate($this->config->get('vorstand_email'), $subjectBoard, 'board_notification', [
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

        // 2. Mail an NUTZER (Zahlung mit EPC-QR)
        $usage     = $this->generateUsageText($p, $shortCode);
        $epcQrData = $this->generateEpcData($p->preisSnapshot, $usage);

        $this->mailService->sendTemplate($p->email, "Zahlung erforderlich: {$p->code}", 'payment_request', [
            'name'           => $p->name,
            'fullIdentifier' => $p->code,
            'betrag'         => \number_format($p->preisSnapshot, 2, ',', '.') . ' €',
            'dueDate'        => (new DateTimeImmutable())->modify('+14 days')->format('d.m.Y'),
            'kontoinhaber'   => $this->config->get('kontoinhaber'),
            'iban'           => $this->config->get('iban'),
            'usage'          => $usage,
            'epcData'        => \urlencode($epcQrData),
        ]);

        // 3. Mail an NUTZER (A4 Dokument)
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
        if (! \filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Die eingegebene E-Mail-Adresse ist ungültig.');
        }
    }
}
