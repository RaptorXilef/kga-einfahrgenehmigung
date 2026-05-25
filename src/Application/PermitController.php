<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

/**
 * Controller für den regulären, öffentlichen Genehmigungs-Beantragungsprozess.
 *
 * Verarbeitet die Formularübermittlung und leitet die E-Mail-Validierungsschleife ein.
 *
 * Path: src/Application/PermitController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
    ) {
    }

    /**
     * Nimmt Antragsdaten entgegen oder zeigt die Erfolgsmeldung nach Erstübermittlung an.
     * Führt bei POST-Aktionen ein `createPendingVerification` aus und triggert Redirects.
     *
     * @param array<string, mixed> $post Entspricht $_POST
     * @param array<string, mixed> $get  Entspricht $_GET
     */
    public function handleRequest(array $post, array $get): void
    {
        $message = '';
        $success = false;

        // 1. Verarbeitung (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Wir speichern den Antrag nur zwischen und senden die Bestätigungsmail.
                // Erst nach Klick auf den Link wird der Gutschein oder die Zahlung relevant.
                $this->permitService->createPendingVerification($post);

                // Nach Erfolg: Redirect, um F5-Doppelabsendung zu verhindern
                \header('Location: index.php?sent=1');
                exit;
            } catch (\Exception $exception) {
                $message = 'Fehler: ' . $exception->getMessage();
            }
        }

        // 2. Nachricht nach Redirect abfangen (GET)
        if (isset($get['sent'])) {
            $success = true;
            $message = 'Bestätigung erforderlich! Wir haben Ihnen eine E-Mail gesendet. '
                . 'Bitte klicken Sie auf den Link darin, um Ihren Antrag zu aktivieren.';
        }

        // 3. View rendern (wie gehabt)
        $this->render('formular', [
            'message'           => $message,
            'success'           => $success,
            'config'            => $this->config,
            'settings'          => $this->getSettingsArray(),
            'appRoot'           => $this->config->get('root_path'),
            'hasActiveVouchers' => $this->checkAvailableVouchers(), // Prüfen, ob einlösbare Gutscheine existieren
        ]);
    }

    /**
     * Prüft, ob mindestens ein Gutschein im System ist, der aktuell gültig ist.
     *
     * @return bool True, wenn mindestens ein einlösbarer Gutschein vorhanden ist.
     */
    private function checkAvailableVouchers(): bool
    {
        $voucherService = $this->permitService->getVoucherService();
        $vouchers       = $voucherService->loadVouchers();

        foreach ($vouchers as $v) {
            if ($voucherService->isValid($v)) {
                return true; // Sobald einer gefunden wurde, reicht das für die Anzeige
            }
        }

        return false;
    }

    /**
     * Baut Einstellungen, Sichtbarkeiten und Farbcodes für das Antragsformular zusammen.
     *
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        $templates = (array) $this->config->get('permit_templates', []);
        $public    = \array_filter($templates, fn ($t) => ($t['public'] ?? false) === true);

        return [
            'vereins_name'     => $this->config->get('vereins_name'),
            'vehicle_types'    => $this->config->get('vehicle_types'),
            'purposes'         => $this->config->get('purposes'),
            'public_templates' => $public,
            'base_url'         => $this->config->getBaseUrl(), // FIX: Das war der Grund für die Fehlermeldungen!
            'jahresFarbe'      => $this->config->get('jahresFarbe'),
        ];
    }

    /**
     * Integriert Variablen-Scope und bindet PHTML-Antragsformulare ein.
     *
     * @param string               $templatePath Template-Name.
     * @param array<string, mixed> $data         UI-Daten.
     */
    private function render(string $templatePath, array $data = []): void
    {
        $appRoot = (string) $this->config->get('root_path');
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
