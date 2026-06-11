<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;

/**
 * Controller für die Checkout-Übersicht.
 *
 * Zeigt dem Benutzer vor dem finalen Zahlungsabschluss eine Zusammenfassung
 * der Antragsdaten und die berechneten Einfahrtszeiten.
 *
 * Path: src/Application/CheckoutController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CheckoutController
{
    public function __construct(
        private ConfigInterface $config,
        private HolidayService $holidayService, // Für die Öffnungszeiten
        private PermitService $permitService,
    ) {
    }

    /**
     * Haupt-Request-Handler für den Checkout-Prozess.
     *
     * Verifiziert das übergebene Token, validiert die Session-Daten und rendert
     * die Zusammenfassungs-Seite mit Feiertagsberechnung.
     *
     * @param array<string, mixed> $get Entspricht $_GET.
     */
    public function handleRequest(array $get): void
    {
        $token    = (string) ($get['token'] ?? '');
        $tempData = $this->permitService->getVerifiedRequest($token);

        if ($token === '' || $tempData === null) {
            // Entweder Link falsch oder Session abgelaufen/bereits bezahlt
            \header('Location: index.php');
            exit;
        }

        // Daten für die Feiertage berechnen
        $dtVon = new \DateTimeImmutable($tempData['datum_von'] ?? 'now');
        $dtBis = new \DateTimeImmutable($tempData['datum_bis'] ?? 'now');

        $this->render('checkout/summary', [
            'token'    => $token,
            'tempData' => $tempData,
            'settings' => $this->getSettingsArray(),
            'config'   => $this->config,
            'appRoot'  => $this->config->get('root_path'),
            // Dem Template die Zeiten übergeben
            'opening'       => $this->holidayService->getOpeningHoursTextForDateRange($dtVon, $dtBis),
            'holidayNotice' => $this->holidayService->getHolidaysInRangeText($dtVon, $dtBis, true),
        ]);
    }

    /**
     * Extrahiert Datenvariablen und bindet das PHTML-Template ein.
     *
     * @param string               $templatePath Relativer Pfad zum Template.
     * @param array<string, mixed> $data         Injektionsdaten für den View-Scope.
     */
    private function render(string $templatePath, array $data = []): void
    {
        // Zwingender Sicherheits-Fix gegen Variable Overwrite / LFI
        \extract($data, \EXTR_SKIP);
        include $this->config->get('root_path') . "/templates/pages/{$templatePath}.phtml";
    }

    /**
     * Liefert standardisierte Konfigurationswerte für das Checkout-Template.
     *
     * @return array<string, mixed> Array mit Vereinsmetadaten und Base-URL.
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'  => $this->config->get('vereins_name'),
            'vehicle_types' => $this->config->get('vehicle_types'),
            'purposes'      => $this->config->get('purposes'),
            'base_url'      => $this->config->getBaseUrl(),
        ];
    }
}
