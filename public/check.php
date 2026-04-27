<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Validierungsschnittstelle für Ausnahmegenehmigungen (v0.8.0).
 *
 * Prüft die Gültigkeit eines Codes und unterscheidet mittels Token-Validierung
 * zwischen der öffentlichen Ansicht und der detaillierten Vorstandsansicht.
 *
 * @file      public/check.php
 *
 * @copyright (c) 2021-2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
 *
 * @since     0.2.0 - Initiale Erstellung.
 * @since     0.2.0 - Refactor(arch): Umstellung auf PermitService und Anker-Pfad-System.
 * @since     0.4.0 - refactor(arch): Move to root structure and blueprint tools.
 */

declare(strict_types=1);

/**
 * FLEXIBLES ANKER-SYSTEM
 * Sucht den Projekt-Root (wo vendor/ liegt) ausgehend vom aktuellen Verzeichnis.
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
use App\Infrastructure\Config\Config;

// Session starten für Admin-Check
if (\session_status() === \PHP_SESSION_NONE) {
    \session_start();
}

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$container             = new Container(new Config($settings));

/** @var StorageInterface $storage */
$storage = $container->get(StorageInterface::class);

$code   = \strtoupper(\trim($_GET['code'] ?? ''));
$permit = $code !== '' && $code !== '0' ? $storage->findByHash($code) : null;

// Fehler setzen, wenn Suche erfolglos
$error = $code && ! $permit ? "Der Code '{$code}' wurde nicht gefunden." : null;

// --- ADMIN-CHECK LOGIK (v0.8.0) ---

// 1. Der neue "Admin-Gott-Modus" aus der Config
$isDevAdmin = (bool) ($settings['admin_dev_mode'] ?? false);

// 2. Über eine aktive Session (Eingeloggt im Admin-Bereich)
// Wir prüfen auf Level 1 oder 2, wie in deinem ALT-Stand
$isSessionAdmin = isset($_SESSION['admin_level']);

// 3. Über den Token im Link (Direkt-Link aus der Vorstands-E-Mail)
$token        = (string) ($_GET['token'] ?? '');
$isTokenAdmin = $permit && \hash_equals(\hash('sha256', $permit->code . $settings['geheimnis']), $token);

// Zusammenführung: Wenn einer der drei Punkte wahr ist, zeige die Admin-Ansicht
$showAdminView = $isDevAdmin || $isSessionAdmin || $isTokenAdmin;

$config = $container->get(Config::class);

// Falls kein Permit gefunden wurde oder die Seite ohne Parameter aufgerufen wird
if (! $permit) {
    include $appRoot . '/templates/pages/check_search.phtml';
    exit;
}

// Wenn gefunden, wähle Template
$templatePath = $showAdminView ? 'check_admin' : 'check_public';
include $appRoot . "/templates/pages/{$templatePath}.phtml";
