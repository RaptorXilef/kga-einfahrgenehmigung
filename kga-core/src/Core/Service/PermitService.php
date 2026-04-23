<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service zur Verwaltung des Genehmigungsprozesses.
 *
 * Orchestriert die Erstellung, Validierung, Speicherung und Benachrichtigung.
 * Inklusive Schutz gegen doppelte Verarbeitungen (Mail-Kaskaden).
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
 * - feat(core): Initialer PermitService zur Workflow-Steuerung.
 * - feat(payment): Methode completePayment zur sicheren Verifizierung hinzugefügt.
 * - fix(logic): Status-Check hinzugefügt, um doppelte Mails zu verhindern.
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Infrastructure\Config\Config;
use RuntimeException;

final class PermitService
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly MailServiceInterface $mailService,
        private readonly PaymentProviderInterface $paymentProvider,
        private readonly Config $config
    ) {
    }

    /**
     * Workflow: Neuer Antrag (Banküberweisung)
     */
    public function createPendingPermit(array $data): Permit
    {
        $this->validateInput($data);

        $type = $data['typ'] ?? 'pkw';
        $kennzeichen = (string) $data['kennzeichen'];

    // LKW / Lieferanten-Logik (Auftragserfüllung)
        if ($type === 'lkw') {
            $firma = !empty($data['firma']) ? (string)$data['firma'] : 'Unbekannt';
            $kennzeichen = "LIEFERANT: " . $firma . " (" . $kennzeichen . ")";
        }

        $duration = $this->config->getPermitDuration();

        $permit = new Permit(
            code:        $this->generateSmartCode(),
            name:        (string) $data['name'],
            email:       (string) $data['email'],
            kennzeichen: $kennzeichen,
            parzelle:    (string) $data['parzelle'],
            typ:         $type,
            zweck:       (string) ($data['zweck'] ?? 'Privat'),
            von:         new \DateTimeImmutable($data['datum_von']),
            bis:         (new \DateTimeImmutable($data['datum_von']))->modify("+$duration days"),
            status:      'wartend'
        );

        if (!$this->storage->save($permit)) {
            throw new \RuntimeException("Kritischer Speicherfehler in der Persistenz-Schicht.");
        }

        $this->notifyBoard($permit);
        return $permit;
    }

    /**
     * PayPal Capture mit dynamischer Preisprüfung
     */
    public function completePayment(string $code, string $orderId): bool
    {
        // 1. Sicherheit: Existiert die Genehmigung überhaupt?
        $permit = $this->storage->findByHash($code);
        if (!$permit || $permit->status === 'bezahlt') {
            return $permit !== null;
        }

    // SICHERHEIT: Den korrekten Preis für diesen Fahrzeugtyp ermitteln
        $expectedPrice = $this->config->getPriceForType($permit->typ);

    // Preis-Matching beim Capture (Verhindert Erschleichen von LKW-Einfahrten zum PKW-Preis)
        if (!$this->paymentProvider->captureOrder($orderId, $expectedPrice)) {
            return false;
        }

        return $this->updateStatus($permit, 'bezahlt');
    }

    /**
     * Workflow: Manuelle Freischaltung (Admin)
     */
    public function manualActivate(string $code): bool
    {
        $permit = $this->storage->findByHash($code);
        if (!$permit || $permit->status === 'bezahlt') {
            return $permit !== null; // True wenn bereits bezahlt, false wenn nicht gefunden
        }

        return $this->updateStatus($permit, 'bezahlt');
    }

    private function updateStatus(Permit $p, string $newStatus): bool
    {
        $updated = new Permit(
            $p->code,
            $p->name,
            $p->email,
            $p->kennzeichen,
            $p->parzelle,
            $p->typ,
            $p->zweck,
            $p->von,
            $p->bis,
            $newStatus,
            $p->erstellt
        );

        $success = $this->storage->save($updated);

        // Mail nur senden, wenn der Status sich wirklich auf 'bezahlt' geändert hat
        if ($success && $newStatus === 'bezahlt') {
            $this->sendApprovalMail($updated);
        }

        return $success;
    }

    private function validateInput(array $data): void
    {
        $required = ['name', 'email', 'kennzeichen', 'parzelle', 'datum_von'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new RuntimeException("Feld fehlt: $field");
            }
        }
    }

    private function generateSmartCode(): string
    {
        $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $code = date('y') . "-";
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    private function notifyBoard(Permit $permit): void
    {
        $token = hash('sha256', $permit->code . $this->config->get('geheimnis'));
        $this->mailService->sendTemplate(
            $this->config->get('vorstand_email'),
            "Neuer Antrag: {$permit->code}",
            'admin_notification',
            [
                'code' => $permit->code,
                'name' => $permit->name,
                'adminLink' => $this->config->get('base_url') . "admin.php?code={$permit->code}&token={$token}",
            ]
        );
    }

    private function sendApprovalMail(Permit $permit): void
    {
        $checkUrl = $this->config->get('base_url') . "check.php?code=" . $permit->code;

        $this->mailService->sendTemplate(
            $permit->email,
            "Ihre Einfahrgenehmigung {$permit->code} - {$this->config->get('vereins_name')}",
            'permit_issued',
            [
                'name'         => $permit->name,
                'code'         => $permit->code,
                'kennzeichen'  => $permit->kennzeichen,
                'parzelle'     => $permit->parzelle,
                'zweck'        => $permit->zweck,
                'von'          => $permit->von->format('d.m.Y'),
                'bis'          => $permit->bis->format('d.m.Y'),
                'jahresFarbe'  => $this->config->get('jahresFarbe', '#2ecc71'),
                'vereinsName'  => $this->config->get('vereins_name'),
                'qrCodeUrl'    => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data="
                    . urlencode($checkUrl),
                'checkUrl'     => $checkUrl,
            ]
        );
    }
}
