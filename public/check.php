<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Validierungsschnittstelle für Ausnahmegenehmigungen (v0.4.0).
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
if (\session_status() === PHP_SESSION_NONE) {
    \session_start();
}

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$container             = new Container(new Config($settings));

/** @var StorageInterface $storage */
$storage = $container->get(StorageInterface::class);
$code    = \strtoupper(\trim($_GET['code'] ?? ''));
$permit  = $storage->findByHash($code);

if (! $permit) {
    // Hier könnten wir später ein schönes 404-Template laden
    exit('Genehmigung mit dem Code ' . \htmlspecialchars($code) . ' wurde nicht gefunden.');
}

// ADMIN-CHECK
// 1. Entweder über den Token im Link (Direkt-Link aus Mail)
$token         = (string) ($_GET['token'] ?? '');
$expectedToken = \hash('sha256', $permit->code . $settings['geheimnis']);
$isTokenAdmin  = \hash_equals($expectedToken, $token);

// 2. Oder über eine aktive Session (Eingeloggt im Admin-Bereich)
$isSessionAdmin = isset($_SESSION['admin_level']) && $_SESSION['admin_level'] <= 2;

$showAdminView = $isTokenAdmin || $isSessionAdmin;

// Weiche: Welches Template laden?
$templatePath = $showAdminView ? 'check_admin' : 'check_public';
include $appRoot . "/templates/pages/{$templatePath}.phtml";
