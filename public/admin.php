<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Admin-Zentrale für den Vorstand (v0.9.2).
 *
 * @file      public/admin.php
 */

declare(strict_types=1);

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
    // Setze die Fehlermeldung nur, wenn wir danach NICHT eingeloggt sind
    // (verhindert die Meldung im admin_dev_mode)
    if (! $auth->isLoggedIn()) {
        $message = 'Ungültige Anmeldedaten.';
    }
}

// --- AUTH-GATEKEEPER ---

if (! $auth->isLoggedIn()) {
    // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
    $msg = $message; // Hilfsvariable für Linter, da in Template genutzt
    include $appRoot . '/templates/pages/admin_login.phtml';
    exit;
}

/**
 * @var string $adminUser
 *
 * phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
 */
$adminUser = $_SESSION['admin_user'] ?? 'Admin';
/**
 * @var int $adminLevel
 *
 * phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
 */
$adminLevel = (int) ($_SESSION['admin_level'] ?? 1);

// --- 2. PRINT PREVIEW (Vor den Dashboard-Daten) ---
if (isset($_GET['action']) && $_GET['action'] === 'print' && isset($_GET['code'])) {
    $permit = $storage->findByHash((string) $_GET['code']);
    if ($permit) {
        include $appRoot . '/templates/pages/admin_print_view.phtml';
        exit;
    }
    $message = 'Genehmigung nicht gefunden.';
}

// --- 3. LOGGED-IN ACTIONS ---

// Zahlung markieren (Level 1 & 2)
// Da wir die ID jetzt in $p->code speichern (der ML-... String):
if (
    isset($_POST['action'])
    && $_POST['action'] === 'mark_as_paid'
    && $permitService->manualActivate((string) $_POST['code'])
) {
    /** @var string $message phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable */
    $message = "Zahlung für {$_POST['code']} bestätigt.";
}

// --- 4. FILTER & EXPORT ---

$filterStart = $_GET['start'] ?? \date('Y-01-01');
$filterEnd   = $_GET['end'] ?? \date('Y-12-31');
$allPermits  = $storage->getAll();

// Statistik-Filter (Basierend auf Erstellungsdatum)
$filtered = \array_filter($allPermits, function ($p) use ($filterStart, $filterEnd): bool {
    $date = $p->erstellt->format('Y-m-d');

    return $date >= $filterStart && $date <= $filterEnd;
});

// Export-Logik (unverändert wie zuvor)
if (isset($_GET['export'])) {
    $format   = $_GET['export'];
    $filename = "export_kga_{$filterStart}_bis_{$filterEnd}";

    if ($format === 'csv') {
        \header('Content-Type: text/csv; charset=utf-8');
        \header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = \fopen('php://output', 'w');

        // 1. FEINHEIT: UTF-8 BOM für Excel (zwingend nötig für Umlaute)
        \fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF)); // BOM

        // FIX: Hinzufügen der Parameter für Umschließung und Escape (verhindert Warning)
        \fputcsv($output, [
            'Kennung',
            'Name',
            'E-Mail',
            'Parzelle',
            'Typ',
            'Kennzeichen',
            'Firma/Lieferant',
            'Zweck',
            'Einnahme (€)',
            'Status',
            'Erstellt am',
        ], ';', '"', '\\');

        foreach ($filtered as $p) {
            \fputcsv($output, [
                $p->code,
                $p->name,
                $p->email,
                $p->parzelle,
                $settings['vehicle_types'][$p->typ] ?? $p->typ,
                $p->kennzeichen,
                $p->firma ?? '',
                $settings['purposes'][$p->zweck] ?? $p->zweck,
                \number_format($p->preisSnapshot, 2, ',', ''),
                \strtoupper((string) $p->status),
                $p->erstellt->format('d.m.Y H:i'),
            ], ';', '"', '\\');
        }

        \fclose($output);
        exit;
    }

    if ($format === 'json') {
        \header('Content-Type: application/json');
        \header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo \json_encode(\array_values($filtered), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// --- 5. STATISTIKEN & GRUPPIERUNG ---
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
$now    = new \DateTimeImmutable('today');
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

/**
 * @var Config $config
 *
 * phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
 */
$config = $container->get(Config::class);
include $appRoot . '/templates/pages/admin_dashboard.phtml';
