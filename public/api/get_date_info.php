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

use App\Core\Service\HolidayService;

\header('Content-Type: application/json');

try {
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

    $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken  = $_SESSION['csrf_token'] ?? '';

    if ($sessionToken === '' || ! \hash_equals($sessionToken, $providedToken)) {
        \http_response_code(401);
        echo \json_encode(['success' => false, 'error' => 'Unauthorized: Invalid Security Token']);
        exit;
    }

    $vonStr = (string) ($_GET['von'] ?? 'now');
    $bisStr = (string) ($_GET['bis'] ?? 'now');

    try {
        $von = new \DateTimeImmutable($vonStr);
        $bis = new \DateTimeImmutable($bisStr);
    } catch (\Exception) {
        $von = new \DateTimeImmutable('today');
        $bis = new \DateTimeImmutable('today');
    }

    $holidayService = $container->get(HolidayService::class);

    // Generiert exakt das Wording aus den E-Mails
    $openingHtml   = '<strong>⏰ Erlaubte Einfahrzeiten (Ruhezeiten beachten):</strong><br>Das Befahren der Anlage ist ausschließlich zu folgenden Zeiten gestattet:<br><span style="color: #333;">' . $holidayService->getGeneralOpeningHoursText() . '</span>';
    $holidayNotice = $holidayService->getHolidaysInRangeText($von, $bis, true);

    echo \json_encode([
        'success'       => true,
        'openingHours'  => $openingHtml,
        'holidayNotice' => $holidayNotice,
    ], \JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo \json_encode(['success' => false, 'error' => $e->getMessage()]);
}
