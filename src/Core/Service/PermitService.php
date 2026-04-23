<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service zur Verwaltung des Genehmigungsprozesses.
 *
 * Orchestriert die Erstellung, Validierung, Speicherung und Benachrichtigung
 * für neue Einfahrgenehmigungen.
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
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Infrastructure\Config\Config;
use DateTimeImmutable;
use RuntimeException;

final class PermitService
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly MailServiceInterface $mailService,
        private readonly Config $config
    ) {
    }

    /**
     * Startet den Prozess für eine neue Genehmigung (Typ: Überweisung/Wartend).
     *
     * @param array $data Rohdaten aus dem Formular.
     *
     * @return Permit Die erstellte Entität.
     */
    public function createPendingPermit(array $data): Permit
    {
        // 1. Validierung (vereinfacht)
        $this->validateInput($data);

        // 2. Code generieren
        $code = $this->generateSmartCode();

        // 3. Entität erstellen
        $permit = new Permit(
            code:        $code,
            name:        $data['name'],
            email:       $data['email'],
            kennzeichen: $data['kennzeichen'],
            parzelle:    $data['parzelle'],
            von:         new DateTimeImmutable($data['datum_von']),
            bis:         (new DateTimeImmutable($data['datum_von']))->modify('+6 days'),
            status:      'wartend'
        );

        // 4. Speichern
        if (!$this->storage->save($permit)) {
            throw new RuntimeException("Fehler beim Speichern der Genehmigung.");
        }

        // 5. Vorstand benachrichtigen (Optional via Config)
        $this->notifyBoard($permit);

        return $permit;
    }

    private function validateInput(array $data): void
    {
        $required = ['name', 'email', 'kennzeichen', 'parzelle', 'datum_von'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new RuntimeException("Pflichtfeld fehlt: $field");
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Ungültige E-Mail-Adresse.");
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
        $adminToken = hash('sha256', $permit->code . $this->config->get('geheimnis'));
        $adminLink = $this->config->get('base_url') . "admin.php?code={$permit->code}&token={$adminToken}";

        $this->mailService->sendTemplate(
            $this->config->get('vorstand_email'),
            "Neuer Antrag: {$permit->code} ({$permit->kennzeichen})",
            'admin_notification', // templates/emails/admin_notification.phtml
            [
                'code' => $permit->code,
                'name' => $permit->name,
                'kennzeichen' => $permit->kennzeichen,
                'parzelle' => $permit->parzelle,
                'adminLink' => $adminLink,
            ]
        );
    }
}
