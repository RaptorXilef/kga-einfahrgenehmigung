<?php

/**
 * TODO DOCBLOCK
 */

declare(strict_types=1);

use App\Application\Response\JsonResponse;
use App\Core\Service\PermitService;

try {
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

    // Sicherheit: Nur eingeloggte Admins dürfen suchen!
    $auth = $container->get(\App\Core\Service\AuthService::class);
    if (! $auth->isLoggedIn()) {
        JsonResponse::unauthorized('Bitte loggen Sie sich ein.');
    }

    // Such-Parameter auslesen
    $query = \trim((string) ($_GET['q'] ?? ''));
    $page  = \max(1, (int) ($_GET['page'] ?? 1));
    $limit = \max(10, \min(100, (int) ($_GET['limit'] ?? 50))); // Max 100 pro Seite

    // Filter-Parameter (z.B. 'all', 'active', 'expired', 'archive')
    $filterTab      = (string) ($_GET['tab'] ?? 'all');
    $filterTemplate = (string) ($_GET['template'] ?? 'all');

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
