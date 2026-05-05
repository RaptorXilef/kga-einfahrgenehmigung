<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Orchestriert die Validierung von Genehmigungen für Pächter und Vorstand.
 *
 * Path:      src/Application/CheckController.php
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
        $now   = new \DateTimeImmutable();

        // 1. Suche in echten Permits (zuerst via Code/Hash)
        $permit = $code !== '' ? $this->storage->findByHash($code) : null;

        // Wenn über den Code nichts gefunden wurde, versuche es als Kennzeichen
        if ($permit === null && $code !== '') {
            $permit = $this->storage->findByLicensePlate($code);
        }

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

        // --- Logik für den nächsten befahrbaren Slot ---
        $nextAllowedSlotText = 'Keine weitere Einfahrt möglich.';
        $nextSlot            = $this->holidayService->getNextAvailableSlot($now);

        if ($nextSlot !== null) {
            // Prüfung: Ist der nächste Slot noch innerhalb der Genehmigungszeit?
            // Spezialfall: Letzter Tag / Ablaufprüfung
            if ($permit instanceof Permit && $nextSlot > $permit->validity->bis) {
                $nextAllowedSlotText = 'Die Gültigkeit endet, bevor die Anlage wieder befahren werden darf.';
            } else {
                // Normale Zeit-Formatierung
                $datePart = $nextSlot->format('d.m.Y');
                $today    = $now->format('d.m.Y');
                $tomorrow = $now->modify('+1 day')->format('d.m.Y');

                if ($datePart === $today) {
                    // "heute ab 15:00 Uhr"
                    $nextAllowedSlotText = 'heute ab ' . $nextSlot->format('H:i') . ' Uhr';
                } elseif ($datePart === $tomorrow) {
                    // "morgen ab 08:00 Uhr"
                    $nextAllowedSlotText = 'morgen ab ' . $nextSlot->format('H:i') . ' Uhr';
                } else {
                    // "am 04.05.2026 ab 08:00 Uhr"
                    $nextAllowedSlotText = 'am ' . $datePart . ' ab ' . $nextSlot->format('H:i') . ' Uhr';
                }
            }
        }

        // Fall 2: Warteraum / Bezahlseite
        if ($tempRequest !== null && ! $permit instanceof Permit) {
            $this->render('check/public', \array_merge($adminData, [
                'isWaitingForPayment' => true,
                'tempData'            => $tempRequest,
                'token'               => $token,
                'isDateValid'         => true,
                'isTimeAllowed'       => $this->holidayService->isTimeAllowedNow(),
                'allowedToday'        => $nextAllowedSlotText,
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
                'allowedToday'  => $nextAllowedSlotText, // Variable wird hier übergeben
                'showAdminView' => $showAdminView,
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
