<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service zur Verwaltung des Genehmigungsprozesses (v0.3.0).
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
 * Zentraler Service für Ausnahmegenehmigungen (v0.4.0).
 */
final readonly class PermitService
{
    public function __construct(
        private StorageInterface $storage,
        private MailServiceInterface $mailService,
        private Config $config,
        private HolidayService $holidayService, // Neu injiziert
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
        $code     = $this->generateV4Code($parzelle);

        $permit = new Permit(
            code: $code,
            name: (string) $data['name'],
            email: (string) $data['email'],
            parzelle: $parzelle,
            typ: $typ,
            kennzeichen: $this->formatLicensePlate((string) ($data['kennzeichen'] ?? '')),
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
        $this->dispatchMails($permit);

        return $permit;
    }

    /**
     * Formatiert deutsche Kennzeichen (z.B. BHD7398 -> B-HD 7398).
     */
    private function formatLicensePlate(string $plate): string
    {
        $plate = \strtoupper((string) \preg_replace('/[^A-Za-z0-9]/', '', $plate));

        // Erkennt 1-3 Buchstaben (Stadt), dann 1-2 Buchstaben (Bezirk), dann Zahlen
        return \preg_replace('/^([A-Z]{1,3})([A-Z]{1,2})(\d{1,4})$/', '$1-$2 $3', $plate) ?? $plate;
    }

    /**
     * Generiert den neuen v4 Code: [PREFIX]-[YY]-[0000]-[RAND]
     */
    private function generateV4Code(string $parzelle): string
    {
        $prefix = $this->config->get('prefix', 'ML');
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $random = '';
        for ($i = 0; $i < 4; ++$i) {
            $random .= $chars[\random_int(0, \strlen($chars) - 1)];
        }

        return \sprintf('%s-%s-%s-%s', $prefix, \date('y'), $parzelle, $random);
    }

    /**
     * Orchestriert den Versand der drei unterschiedlichen E-Mails.
     */
    private function dispatchMails(Permit $p): void
    {
        $zeitraum = "{$p->von->format('d.m.Y')} bis {$p->bis->format('d.m.Y')}";
        $opening  = $this->config->get('opening_hours');

        // 1. Mail an VORSTAND
        $token        = \hash('sha256', $p->code . $this->config->get('geheimnis'));
        $subjectBoard = "[ML-{$p->parzelle}, {$p->kennzeichen}, {$p->code}] - {$zeitraum} - {$p->name}";

        $this->mailService->sendTemplate($this->config->get('vorstand_email'), $subjectBoard, 'board_notification', [
            'permit'    => $p,
            'adminLink' => $this->config->get('base_url') . "admin.php?code={$p->code}&token={$token}",
        ]);

        // 2. Mail an NUTZER (Zahlung)
        $dueDays = $this->config->get('payment_due_days', 14);
        $dueDate = (new DateTimeImmutable())->modify("+{$dueDays} days")->format('d.m.Y');

        $this->mailService->sendTemplate(
            $p->email,
            "Zahlungsaufforderung für Genehmigung {$p->code}",
            'payment_request',
            [
                'name'             => $p->name,
                'betrag'           => \number_format($p->preisSnapshot, 2, ',', '.') . ' €',
                'dueDate'          => $dueDate,
                'iban'             => $this->config->get('iban'),
                'verwendungszweck' => "Einfahrt {$p->code}, Parzelle {$p->parzelle}",
            ],
        );

        // 3. Mail an NUTZER (Die eigentliche Ausnahmegenehmigung - A4 Print)
        $subjectUser = 'Ausnahmegenehmigung zum Befahren der Anlage: ' . $this->config->get('vereins_name');

        $this->mailService->sendTemplate($p->email, $subjectUser, 'permit_a4_document', [
            'permit'      => $p,
            'jahresFarbe' => $this->config->get('jahresFarbe'),
            'opening'     => "{$opening['earliest']} bis {$opening['latest']} Uhr",
            'qrUrl'       => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' .
                \urlencode($this->config->get('base_url') . 'check.php?code=' . $p->code),
        ]);
    }

    private function validateEmail(string $email): void
    {
        if (! \filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
        }
    }
}
