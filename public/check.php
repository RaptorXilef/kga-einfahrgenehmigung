<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Validierungsschnittstelle für Einfahrgenehmigungen.
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
$appRoot = (function() {
    $dir = __DIR__;
    // Wir suchen nach oben, bis wir den Ordner finden, der 'vendor' oder 'src' enthält
    while ($dir !== dirname($dir)) {
        if (file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = dirname($dir);
    }
    // Fallback: Falls nichts gefunden wurde, gehen wir eine Ebene hoch
    return dirname(__DIR__);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;

$container = new Container(new Config($settings));

/** @var StorageInterface $storage */
$storage = $container->get(StorageInterface::class);
$code    = \strtoupper(\trim($_GET['code'] ?? ''));
$permit  = $storage->findByHash($code);

if (! $permit) {
    // Mentoring: Ein professionelles Error-Handling lädt auch hier ein Template
    exit('Genehmigung mit dem Code ' . \htmlspecialchars($code) . ' wurde nicht gefunden.');
}

// Admin-Prüfung via Token (Sicherheit: hash_equals gegen Timing-Attacks)
$token         = (string) ($_GET['token'] ?? '');
$expectedToken = \hash('sha256', $permit->code . $settings['geheimnis']);
$isAdmin       = \hash_equals($expectedToken, $token);

// Weiche: Welches Template laden?
$templatePath = $isAdmin ? 'check_admin' : 'check_public';
include $appRoot . "/templates/pages/{$templatePath}.phtml";
