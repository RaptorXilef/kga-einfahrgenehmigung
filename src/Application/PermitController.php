<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Orchestriert den Antragsprozess für Pächter (Hauptformular).
 *
 * @file      public/index.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

/**
 * Orchestriert den Antragsprozess für Pächter (Hauptformular).
 */
final readonly class PermitController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
    ) {
    }

    /**
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $post): void
    {
        $message = '';
        $success = false;

        // Formular-Verarbeitung (nur bei POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Wir speichern den Antrag nur zwischen und senden die Bestätigungsmail.
                // Erst nach Klick auf den Link wird der Gutschein oder die Zahlung relevant.
                $this->permitService->createPendingVerification($post);

                $success = true;
                $message = 'Bestätigung erforderlich! Wir haben Ihnen eine E-Mail gesendet. Bitte klicken Sie auf den Link darin, um Ihren Antrag (und ggf. Gutschein) zu aktivieren.';
            } catch (\Exception $exception) {
                $message = 'Fehler: ' . $exception->getMessage();
            }
        }

        // View rendern
        $this->render('formular', [
            'message'  => $message,
            'success'  => $success,
            'config'   => $this->config,
            'settings' => $this->getSettingsArray(),
            'appRoot'  => $this->config->get('root_path'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'  => $this->config->get('vereins_name'),
            'vehicle_types' => $this->config->get('vehicle_types'),
            'purposes'      => $this->config->get('purposes'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $templatePath, array $data = []): void
    {
        $appRoot = (string) $this->config->get('root_path');
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
