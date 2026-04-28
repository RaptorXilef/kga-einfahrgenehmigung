<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Orchestriert die Validierung von Genehmigungen für Pächter und Vorstand.
 *
 * @file      src/Application/CheckController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Infrastructure\Auth\AuthService;

/**
 * Orchestriert die Validierung von Genehmigungen für Pächter und Vorstand.
 */
final readonly class CheckController
{
    public function __construct(
        private ConfigInterface $config,
        private StorageInterface $storage,
        private AuthService $auth,
    ) {
    }

    /**
     * @param array<string, mixed> $get Entspricht $_GET
     */
    public function handleRequest(array $get): void
    {
        $code   = \strtoupper(\trim((string) ($get['code'] ?? '')));
        $permit = $code !== '' && $code !== '0' ? $this->storage->findByHash($code) : null;

        // 1. Fall: Kein Code eingegeben oder Seite direkt aufgerufen
        if ($code === '') {
            $this->render('check_search', ['error' => null]);

            return;
        }

        // 2. Fall: Code wurde nicht gefunden
        if (! $permit instanceof Permit) {
            $this->render('check_search', [
                'error' => "Der Code '{$code}' wurde nicht gefunden.",
            ]);

            return;
        }

        // 3. Fall: Permit gefunden -> Admin-Check
        $showAdminView = $this->determineViewPrivileges($permit, $get);
        $template      = $showAdminView ? 'check_admin' : 'check_public';

        $this->render($template, [
            'permit'   => $permit,
            'config'   => $this->config,
            'settings' => $this->getSettingsArray(),
        ]);
    }

    /**
     * Prüft, ob der Nutzer erweiterte Details sehen darf.
     *
     * @param array<string, mixed> $get
     */
    private function determineViewPrivileges(Permit $permit, array $get): bool
    {
        // A. Entwickler-Modus
        if ((bool) $this->config->get('admin_dev_mode', false)) {
            return true;
        }

        // B. Eingeloggter Admin (Session)
        if ($this->auth->isLoggedIn()) {
            return true;
        }

        // C. Token im Link (SHA256 Abgleich)
        $token     = (string) ($get['token'] ?? '');
        $geheimnis = (string) $this->config->get('geheimnis', '');
        $expected  = \hash('sha256', $permit->code . $geheimnis);

        return \hash_equals($expected, $token);
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
            'opening_hours' => $this->config->get('opening_hours'),
            'jahresFarbe'   => $this->config->get('jahresFarbe'),
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
