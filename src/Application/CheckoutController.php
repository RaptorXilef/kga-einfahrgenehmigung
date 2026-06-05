<?php

// Path: src\Application\CheckoutController.php
declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;

/**
 * TODO DOCBLOCK
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
        private HolidayService $holidayService, // <-- NEU: Für die Öffnungszeiten
        private PermitService $permitService,
    ) {
    }

    // TODO DOCBLOCK
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
            'opening'       => $this->holidayService->getGeneralOpeningHoursText(),
            'holidayNotice' => $this->holidayService->getHolidaysInRangeText($dtVon, $dtBis, true),
        ]);
    }

    // TODO DOCBLOCK
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'  => $this->config->get('vereins_name'),
            'vehicle_types' => $this->config->get('vehicle_types'),
            'purposes'      => $this->config->get('purposes'),
            'base_url'      => $this->config->getBaseUrl(),
        ];
    }

    // TODO DOCBLOCK
    private function render(string $templatePath, array $data = []): void
    {
        \extract($data);
        include $this->config->get('root_path') . "/templates/pages/{$templatePath}.phtml";
    }
}
