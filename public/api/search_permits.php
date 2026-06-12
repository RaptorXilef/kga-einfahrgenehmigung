<?php

/**
 * API: Administrative Echtzeitsuche
 *
 * Path: public/api/search_permits.php
 *
 * PDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\Response\JsonResponse;
use App\Core\Service\PermitService;

try {
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
    JsonResponse::enforceCsrfProtection();

    // Sicherheit: Nur befugte Admins dürfen die Live-Suche über die API triggern!
    $auth = $container->get(\App\Core\Service\AuthService::class);

    // Überprüfung des exakten Such-Rechts!
    if (! $auth->isLoggedIn() || ! $auth->hasPermission('dashboard.control_bar.search')) {
        JsonResponse::unauthorized('Nicht autorisiert. Such-Berechtigung fehlt.');
    }

    // Such-Parameter auslesen
    // SICHERHEIT: Nur POST erlauben
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::error('Methode nicht erlaubt.', 405);
    }

    // Parameter aus dem POST-Array auslesen statt GET
    $query = \trim((string) ($_POST['q'] ?? ''));
    $page  = \max(1, (int) ($_POST['page'] ?? 1));
    $limit = \max(10, \min(100, (int) ($_POST['limit'] ?? 50)));

    // Filter-Parameter (z.B. 'all', 'active', 'expired', 'archive')
    $filterTab      = (string) ($_POST['tab'] ?? 'all');
    $filterTemplate = (string) ($_POST['template'] ?? 'all');

    // Wir holen den Service und lassen ihn die schwere Arbeit machen
    $permitService = $container->get(PermitService::class);

    // Die neue magische Such-Methode (bauen wir gleich in Schritt 2)
    $result = $permitService->searchAndPaginate($query, $filterTab, $filterTemplate, $page, $limit);

    JsonResponse::success([
        'data' => $result['items'],
        'meta' => [
            'total'       => $result['total'],
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => \ceil($result['total'] / $limit),
        ],
    ]);

} catch (\Throwable $e) {
    JsonResponse::error($e->getMessage(), 500);
}
