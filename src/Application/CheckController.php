<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;

/**
 * Controller zur Überprüfung von Genehmigungen und Kennzeichen.
 *
 * Erlaubt die öffentliche und administrative Abfrage von Gültigkeiten (z.B. via QR-Code).
 * Kontext: Einstiegspunkt für Kontroll-Infrastruktur oder manuelle Suchen.
 *
 * Path: src/Application/CheckController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CheckController
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private PermitService $permitService,
        private StorageInterface $storage,
    ) {
    }

    /**
     * Haupt-Request-Handler für den Validierungs- und Suchprozess.
     * Identifiziert Genehmigungen per Hash oder Kennzeichen und steuert
     * die Ausgabe (Admin-Ansicht, öffentliche Ansicht oder Suchformular).
     *
     * @param array<string, mixed> $get Entspricht $_GET
     */
    public function handleRequest(array $get): void
    {
        $code = \strtoupper(\trim((string) ($get['code'] ?? '')));
        $now  = new \DateTimeImmutable();

        // 1. Suche in echten Permits (zuerst via Code/Hash)
        $permit = $code !== '' ? $this->storage->findByHash($code) : null;

        // Wenn über den Code nichts gefunden wurde, versuche es als Kennzeichen
        if (! $permit instanceof Permit && $code !== '') {
            $permit = $this->storage->findByLicensePlate($code);
        }

        // Fall 1: Nichts eingegeben -> Suchmaske
        if ($code === '') {
            $this->render('check/search', ['error' => null]);

            return;
        }

        // Standard-Daten für die Header-Navigation (falls eingeloggt)
        $adminData = [
            'adminUser'  => $this->auth->getUsername(),
            'adminId'    => $this->auth->getUserId(),
            'adminGroup' => $this->auth->getGroup(),
        ];

        // --- Logik für den nächsten befahrbaren Slot ---
        $nextAllowedSlotText = 'Keine weitere Einfahrt möglich.';
        $nextSlot            = $this->holidayService->getNextAvailableSlot($now);

        if ($nextSlot instanceof \DateTimeImmutable) {
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

        // Fall 2: Genehmigung gefunden
        if ($permit instanceof Permit) {
            $showAdminView = $this->determineViewPrivileges($permit, $get);

            // Config auslesen
            $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);

            // Pfade angepasst auf Unterordner check/
            $this->render($showAdminView ? 'check/admin' : 'check/public', \array_merge($adminData, [
                'permit'        => $permit,
                'isDateValid'   => $permit->isValid($requirePayment),
                'isTimeAllowed' => $this->holidayService->isTimeAllowedNow(),
                'allowedToday'  => $nextAllowedSlotText,
                'showAdminView' => $showAdminView,
                'holidayNotice' => $this->holidayService->getHolidaysInRangeText(
                    $permit->validity->von,
                    $permit->validity->bis,
                    false,
                ),
            ]));

            return;
        }

        // Fall 3: Code/Kennzeichen nicht gefunden
        $this->render('check/search', ['error' => "Code '{$code}' nicht gefunden."]);
    }

    /**
     * Bestimmt die Rechte des aktuellen Betrachters für die Detailansicht.
     * Evaluierte Bedingungen: Admin-Dev-Mode aktiv, Admin eingeloggt oder gültiger Signatur-Hash.
     *
     * @param Permit               $permit Das zu prüfende Genehmigungs-Objekt.
     * @param array<string, mixed> $get    Entspricht $_GET (für Token-Abgleich).
     *
     * @return bool True, wenn erweiterte Admin-Informationen angezeigt werden dürfen.
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

        // C. Token im Link (SHA256 Abgleich) für Vorstandsansicht
        $token     = (string) ($get['token'] ?? '');
        $geheimnis = (string) $this->config->get('geheimnis', '');
        $expected  = \hash('sha256', $permit->code . $geheimnis);

        return \hash_equals($expected, $token);
    }

    /**
     * Liefert standardisierte Konfigurationswerte für die Layout-Generierung.
     *
     * @return array<string, mixed> Array mit Vereinsmetadaten und Fahrzeugtypen.
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'  => $this->config->get('vereins_name'),
            'vehicle_types' => $this->config->get('vehicle_types'),
            'purposes'      => $this->config->get('purposes'),
            'opening_hours' => $this->config->get('opening_hours'),
            'jahresFarbe'   => $this->config->get('jahresFarbe'),
            'base_url'      => $this->config->getBaseUrl(),
        ];
    }

    /**
     * Extrahiert Daten-Arrays und bindet die PHTML-Layoutdatei ein.
     *
     * @param string               $templatePath Relativer Pfad zum Template.
     * @param array<string, mixed> $data         Injektionsdaten für den View-Scope.
     */
    private function render(string $templatePath, array $data = []): void
    {
        $config   = $this->config;
        $appRoot  = (string) $config->get('root_path');
        $settings = $this->getSettingsArray();

        // Hier fügen wir 'auth' hinzu, damit es in JEDEM Template
        // dieses Controllers verfügbar ist (auch im Header-Nav)
        $templateData = \array_merge([
            'appRoot'  => $appRoot,
            'settings' => $settings,
            'config'   => $config,
            'auth'     => $this->auth,
        ], $data);

        // 2. Die Variable an extract übergeben
        \extract($templateData);

        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
