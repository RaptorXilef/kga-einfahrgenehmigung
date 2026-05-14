<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Entry Point: My Profile
 *
 * Path: <public>profile.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// 1. Die Einstellungen aus der Datei laden
$settings = require __DIR__ . '/../config/config.php';

// 2. Den root_path manuell hinzufügen, da er für das Rendering wichtig ist
$settings['root_path'] = \realpath(__DIR__ . '/../') . '/';

// 3. Jetzt das Config-Objekt korrekt mit dem Array initialisieren
$config = new \App\Infrastructure\Config\Config($settings);

// Datenbank-Verbindung (PDO) - Falls du MySQL nutzt, hier initialisieren, sonst null
$pdo = null;
if ($config->get('storage_config')['users']['type'] === 'mysql') {
    $db  = $config->get('database');
    $pdo = new \PDO(
        "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
    );
}

$auth       = new \App\Infrastructure\Auth\AuthService($config, $pdo);
$controller = new \App\Application\UserController($config, $auth);

// Request an den Controller übergeben
$controller->handleProfileRequest($_POST);
