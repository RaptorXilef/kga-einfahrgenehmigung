<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Orchestriert den Double-Opt-In Verifizierungsprozess.
 *
 * @file src/Application/VerificationController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Entity\Permit;
use App\Core\Service\PermitService;

/**
 * Orchestriert den Double-Opt-In Verifizierungsprozess.
 */
final readonly class VerificationController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
    ) {
    }

    /**
     * @param array<string, mixed> $get Entspricht $_GET
     */
    public function handleRequest(array $get): void
    {
        $token  = (string) ($get['token'] ?? '');
        $permit = $this->permitService->confirmEmail($token);

        if ($permit instanceof Permit) {
            // Erfolg: Weiterleitung zur Check-Seite mit Flag für Erfolgsmeldung
            \header('Location: check.php?code=' . $permit->code . '&verified=1');

            return;
        }

        // Fehlerfall: Wir rendern eine kleine Fehlerseite statt exit()
        $this->render('verify_error', [
            'message'  => 'Bestätigungslink ungültig oder bereits abgelaufen.',
            'settings' => $this->getSettingsArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name' => $this->config->get('vereins_name'),
            'jahresFarbe'  => $this->config->get('jahresFarbe'),
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
