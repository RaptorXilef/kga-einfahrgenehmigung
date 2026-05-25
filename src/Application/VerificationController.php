<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Entity\Permit;
use App\Core\Service\MailQueueService;
use App\Core\Service\PermitService;

/**
 * Controller zur Verifizierung von E-Mail-Adressen (Double-Opt-In).
 * Verarbeitet die Validierungscodes aus Links oder manuellen Formulareingaben,
 * finalisiert die Anträge und stößt die Mail-Warteschlange (MailQueueService) an.
 *
 * Path: src/Application/VerificationController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VerificationController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
    ) {
    }

    /**
     * Haupt-Request-Handler für den Double-Opt-In-Prozess.
     * Überprüft Token aus $_GET oder Eingabecodes aus $_POST. Bei erfolgreicher Verifizierung
     * wird die Mail-Queue getriggert und der Nutzer zur Statusprüfung weitergeleitet.
     *
     * @param array<string, mixed> $get  Entspricht $_GET
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $get, array $post): void
    {
        $input = '';

        // 1. Kam die Anfrage über einen Link (?token=...) oder das Formular (POST)?
        if (isset($get['token'])) {
            $input = (string) $get['token'];
        } elseif (isset($post['submit_code'])) {
            $input = (string) ($post['verification_code'] ?? '');
        }

        // 2. Eingabe verarbeiten
        if ($input !== '') {
            $result = $this->permitService->confirmEmail($input);

            // --- NEU: HIER TRIGGERN (Bevor wir die Methode verlassen) ---
            $mailService = $this->permitService->getMailService();
            if ($mailService instanceof MailQueueService) {
                $mailService->processQueue(3); // Dokumente sofort losschicken!
            }

            // Fall A: Sofort finalisiert (z.B. durch Gutschein)
            if (isset($result['finalised']) && $result['finalised'] instanceof Permit) {
                // Erfolg: Weiterleitung zur Check-Seite mit Flag für Erfolgsmeldung
                \header('Location: check.php?code=' . $result['finalised']->code . '&verified=1');

                return;
            }

            // Fall B: Nur E-Mail bestätigt, wartet nun auf Zahlung
            if (\is_array($result)) {
                $redirectToken = $result['actual_token'] ?? $input; // Nutze den echten Key
                \header('Location: check.php?token=' . $redirectToken . '&verified=1');

                return;
            }

            // Fehlerfall: Falscher Code / Abgelaufen -> PRG Redirect zur Eingabemaske
            $msg = 'Der eingegebene Code oder Link ist ungültig bzw. bereits abgelaufen.';
            \header('Location: verify.php?error=1&msg=' . \urlencode($msg));

            return;
        }

        // 3. Ansicht rendern (Eingabemaske)
        $displayMessage = (string) ($get['msg'] ?? '');
        $isError        = isset($get['error']);

        // Wir nennen die Datei jetzt verify_input statt verify_error
        $this->render('verify_input', [
            'message'  => $displayMessage,
            'isError'  => $isError,
            'settings' => $this->getSettingsArray(),
        ]);
    }

    /**
     * Generiert standardisierte Konfigurationsparameter für das Verifizierungs-Frontend.
     *
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name' => $this->config->get('vereins_name'),
            'jahresFarbe'  => $this->config->get('jahresFarbe'),
            'base_url'     => $this->config->getBaseUrl(),
        ];
    }

    /**
     * Extrahiert Daten-Arrays und bindet die Eingabemasken für Verifizierungscodes ein.
     *
     * @param string               $templatePath Name der Layout-Datei.
     * @param array<string, mixed> $data         Injektionsdaten.
     */
    private function render(string $templatePath, array $data = []): void
    {
        $appRoot = (string) $this->config->get('root_path');
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
