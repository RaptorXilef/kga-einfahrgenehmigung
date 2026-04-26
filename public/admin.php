<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Admin-Zentrale für den Vorstand (v0.6.1).
 *
 * @file      public/admin.php
 */

declare(strict_types=1);

/**
 * FLEXIBLES ANKER-SYSTEM
 */
$appRoot = (function (): string {
    $dir = __DIR__;
    while ($dir !== \dirname($dir)) {
        if (\file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = \dirname($dir);
    }

    return \dirname(__DIR__);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\PermitService;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Config\Config;

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$container             = new Container(new Config($settings));

/** @var AuthService $auth */
$auth = new AuthService($container->get(Config::class));
/** @var StorageInterface $storage */
$storage = $container->get(StorageInterface::class);
/** @var PermitService $permitService */
$permitService = $container->get(PermitService::class);

$message = '';

// --- 1. GLOBAL ACTIONS ---

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    \header('Location: admin.php');
    exit;
}

// Login
if (isset($_POST['login'])) {
    if ($auth->login($_POST['user'] ?? '', $_POST['pass'] ?? '')) {
        \header('Location: admin.php');
        exit;
    }
    $message = 'Ungültige Anmeldedaten.';
}

// --- 2. AUTH-GATEKEEPER ---

if (! $auth->isLoggedIn()) {
    include $appRoot . '/templates/pages/admin_login.phtml';
    exit;
}

// --- 3. LOGGED-IN ACTIONS ---

// Zahlung markieren (Level 1 & 2)
// Da wir die ID jetzt in $p->code speichern (der ML-... String):
if (isset($_POST['action']) && $_POST['action'] === 'mark_as_paid' && $permitService->manualActivate((string) $_POST['code'])) {
    $message = "Zahlung für {$_POST['code']} bestätigt.";
}

// --- 4. FILTER & EXPORT ---

$filterStart = $_GET['start'] ?? \date('Y-01-01');
$filterEnd   = $_GET['end'] ?? \date('Y-12-31');

$allPermits = $storage->getAll();

// Statistik-Filter (Basierend auf Erstellungsdatum)
$filtered = \array_filter($allPermits, function ($p) use ($filterStart, $filterEnd): bool {
    $date = $p->erstellt->format('Y-m-d');

    return $date >= $filterStart && $date <= $filterEnd;
});

// Export-Logik (unverändert wie zuvor)
if (isset($_GET['export'])) {
    $format   = $_GET['export'];
    $filename = "export_kga_{$filterStart}_bis_{$filterEnd}";

    if ($format === 'json') {
        \header('Content-Type: application/json');
        \header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo \json_encode(\array_values($filtered), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($format === 'csv') {
        \header('Content-Type: text/csv; charset=utf-8');
        \header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = \fopen('php://output', 'w');
        // UTF-8 BOM für Excel
        \fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));
        \fputcsv($output, ['Kennung', 'Name', 'Parzelle', 'Kennzeichen', 'Typ', 'Einnahme', 'Status', 'Erstellt'], ';');
        foreach ($filtered as $p) {
            \fputcsv($output, [
                $p->code,
                $p->name,
                $p->parzelle,
                $p->kennzeichen,
                $p->typ,
                \number_format($p->preisSnapshot, 2, ',', ''),
                $p->status,
                $p->erstellt->format('Y-m-d H:i'),
            ], ';');
        }
        \fclose($output);
        exit;
    }
}

// --- 5. STATISTIKEN ---

$stats = [
    'total_count'   => \count($filtered),
    'total_revenue' => \array_reduce($filtered, fn ($sum, $p): float|int|array => $sum + $p->preisSnapshot, 0.0),
    'types'         => ['pkw' => 0, 'lkw' => 0],
    'plots'         => [],
];
foreach ($filtered as $p) {
    $stats['types'][$p->typ]      = ($stats['types'][$p->typ] ?? 0) + 1;
    $stats['plots'][$p->parzelle] = ($stats['plots'][$p->parzelle] ?? 0) + 1;
}
\arsort($stats['plots']);

// Dashboard-Gruppen (Basierend auf Gültigkeit von/bis)
$now    = new DateTimeImmutable('today');
$groups = ['active' => [], 'future' => [], 'expired' => []];
foreach ($allPermits as $p) {
    if ($p->bis < $now) {
        $groups['expired'][] = $p;
    } elseif ($p->von > $now) {
        $groups['future'][] = $p;
    } else {
        $groups['active'][] = $p;
    }
}

include $appRoot . '/templates/pages/admin_dashboard.phtml';
