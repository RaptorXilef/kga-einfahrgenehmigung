<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Admin-Oberfläche für den Vorstand.
 *
 * @file      public/admin.php
 *
 * @since     0.1.0
 */

declare(strict_types=1);

/**
 * Admin-Controller (v0.6.0).
 */
$appRoot = (function (): string {
    $dir = __DIR__;
    // Wir suchen nach oben, bis wir den Ordner finden, der 'vendor' oder 'src' enthält
    while ($dir !== \dirname($dir)) {
        if (\file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = \dirname($dir);
    }

    // Fallback: Falls nichts gefunden wurde, gehen wir eine Ebene hoch
    return \dirname(__DIR__);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\PermitService;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Config\Config;

$settings = require_once $appRoot . '/config/config.php';
// Wir injizieren den Root-Pfad in die Config, damit alle Services ihn kennen
$settings['root_path'] = $appRoot;
$container             = new Container(new Config($settings));

$auth = new AuthService($container->get(Config::class));
/** @var PermitService $permitService */
$permitService = $container->get(PermitService::class);
/** @var StorageInterface $storage */
$storage = $container->get(StorageInterface::class);

$message = '';

// --- ACTIONS ---

// A. Login
if (isset($_POST['login']) && ! $auth->login($_POST['user'] ?? '', $_POST['pass'] ?? '')) {
    $message = 'Ungültige Anmeldedaten.';
}

// B. Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    \header('Location: admin.php');
    exit;
}

// C. Zahlung markieren (Level 1 & 2)
if (isset($_POST['action']) && $_POST['action'] === 'mark_as_paid' && $auth->isLoggedIn()) {
    $permitService->manualActivate((string) $_POST['code']);
    $message = "Zahlung für {$_POST['code']} bestätigt.";
}

// --- VIEW LOGIK ---

if (! $auth->isLoggedIn()) {
    include $appRoot . '/templates/pages/admin_login.phtml';
    exit;
}

// Daten laden und gruppieren für das Dashboard
$allPermits = $storage->getAll();
$now        = new DateTimeImmutable('today');

$groups = [
    'active'  => [],
    'future'  => [],
    'expired' => [],
];

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
