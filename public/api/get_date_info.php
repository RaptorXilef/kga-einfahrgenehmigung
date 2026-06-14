<?php

/**
 * API: Ruhe- und Feiertage abrufen
 *
 * Path: public/api/get_date_info.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\Response\JsonResponse;
use App\Application\View\HolidayHtmlPresenter;
use App\Core\Service\HolidayService;

try {
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
    JsonResponse::enforceCsrfProtection();

    // SICHERHEIT: Nur POST erlauben
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::error('Methode nicht erlaubt.', 405);
    }

    // JSON-Stream auslesen
    try {
        $raw   = \file_get_contents('php://input');
        $input = $raw === '' ? [] : \json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        JsonResponse::error('Bad Request: Ungültiges JSON-Format gesendet.', 400);
    }

    $vonStr = (string) ($input['von'] ?? 'now');
    $bisStr = (string) ($input['bis'] ?? 'now');

    try {
        $von = new \DateTimeImmutable($vonStr);
        $bis = new \DateTimeImmutable($bisStr);
    } catch (\Exception) {
        $von = new \DateTimeImmutable('today');
        $bis = new \DateTimeImmutable('today');
    }

    $holidayService = $container->get(HolidayService::class);
    $holidays       = $holidayService->getHolidaysInRange($von, $bis);

    // Generiert exakt das Wording aus den E-Mails
    $openingData = $holidayService->getOpeningHoursDataForDateRange($von, $bis);

    $holidayNotice = HolidayHtmlPresenter::formatHolidayNotice($holidays);
    $openingHtml   = '<strong>⏰ Erlaubte Einfahrzeiten (Ruhezeiten beachten):</strong><br>' .
        'Das Befahren der Anlage ist ausschließlich zu folgenden Zeiten gestattet:<br>' .
        '<span style="color: var(--primary-color); font-weight: bold;">' .
        HolidayHtmlPresenter::formatOpeningHours($openingData) .
        '</span>';

    JsonResponse::success([
        'openingHours'  => $openingHtml,
        'holidayNotice' => $holidayNotice,
    ]);
} catch (\Throwable $e) {
    JsonResponse::error($e->getMessage());
}
