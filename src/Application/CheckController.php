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
use App\Core\Service\HolidayService;
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
        private HolidayService $holidayService,
    ) {
    }

    /**
     * @param array<string, mixed> $get Entspricht $_GET
     */
    public function handleRequest(array $get): void
    {
        $code  = \strtoupper(\trim((string) ($get['code'] ?? '')));
        $token = (string) ($get['token'] ?? ''); // Wir brauchen das Token aus der E-Mail

        // 1. Suche in echten Permits
        $permit = $code !== '' ? $this->storage->findByHash($code) : null;

        // 2. Suche in verifizierten Anträgen (Warteraum 2)
        $verifiedPath = $this->config->get('root_path') . '/storage/verified_pending.json';
        $allVerified  = $this->holidayService->loadJson($verifiedPath); // Methode ggf. in HolidayService public machen oder im Controller loadJson einbauen
        $tempRequest  = $allVerified[$token] ?? null;

        if ($code === '' && ! $tempRequest) {
            $this->render('check_search', ['error' => null]);

            return;
        }

        // Wenn wir im Warteraum 2 sind, rendern wir die Bezahlseite
        if ($tempRequest && ! $permit) {
            $this->render('check_public', [
                'isWaitingForPayment' => true,
                'tempData'            => $tempRequest,
                'token'               => $token,
                'config'              => $this->config,
                'settings'            => $this->getSettingsArray(),
                'appRoot'             => $this->config->get('root_path'),
            ]);

            return;
        }
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
