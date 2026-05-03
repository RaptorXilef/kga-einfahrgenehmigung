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
use App\Core\Service\PermitService;
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
        private PermitService $permitService,
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

        // 2. Suche in verifizierten Anträgen (Warteraum 2) via PermitService
        // Wir nutzen den PermitService, um den Warteraum zu prüfen
        $tempRequest = $this->permitService->getVerifiedRequest($token);

        // Fall 1: Nichts eingegeben -> Suchmaske (Ordner-Pfad angepasst!)
        if ($code === '' && $tempRequest === null) {
            $this->render('check/search', ['error' => null]);

            return;
        }

        // Standard-Daten für die Header-Navigation (falls eingeloggt)
        $adminData = [
            'adminUser'  => (string) ($_SESSION['admin_user'] ?? 'Admin'),
            'adminLevel' => (int) ($_SESSION['admin_level'] ?? 1),
        ];

        // Fall 2: Bezahlseite (Warteraum)
        if ($tempRequest !== null && ! $permit instanceof Permit) {
            $this->render('check/public', \array_merge($adminData, [
                'isWaitingForPayment' => true,
                'tempData'            => $tempRequest,
                'token'               => $token,
                'config'              => $this->config,
                'settings'            => $this->getSettingsArray(),
                'appRoot'             => $this->config->get('root_path'),
                'isDateValid'         => true,
                'isTimeAllowed'       => $this->holidayService->isTimeAllowedNow(),
                'allowedToday'        => $this->holidayService->getTodayAllowedSlots(),
                'showAdminView'       => false,
                'permit'              => null,
            ]));

            return;
        }

        // Fall 3: Genehmigung gefunden
        if ($permit instanceof Permit) {
            $showAdminView = $this->determineViewPrivileges($permit, $get);
            // Pfade angepasst auf Unterordner check/
            $this->render($showAdminView ? 'check/admin' : 'check/public', \array_merge($adminData, [
                'permit'        => $permit,
                'isDateValid'   => $permit->isValid(),
                'isTimeAllowed' => $this->holidayService->isTimeAllowedNow(),
                'allowedToday'  => $this->holidayService->getTodayAllowedSlots(),
                'showAdminView' => $showAdminView,
                'settings'      => $this->getSettingsArray(),
                'config'        => $this->config,
                'appRoot'       => $this->config->get('root_path'),
                'tempData'      => null,
            ]));

            return;
        }

        // Fall 4: Code nicht gefunden
        $this->render('check/search', ['error' => "Code '{$code}' nicht gefunden."]);
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
        $config   = $this->config;
        $appRoot  = (string) $config->get('root_path');
        $settings = $this->getSettingsArray();

        // 1. Array in einer Variable zwischenspeichern (löst den Fehler P1114)
        $templateData = \array_merge([
            'appRoot'  => $appRoot,
            'settings' => $settings,
            'config'   => $config,
        ], $data);

        // 2. Die Variable an extract übergeben
        \extract($templateData);

        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
