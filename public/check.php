<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Einstiegspunkt zum Überprüfen der Gültigkeit der Einfahrgenemigung.
 *
 * @file      check.php
 *
 * @since     0.1.0
 */

declare(strict_types=1);

// ==========================================================
// DIE EINZIGE VARIABLE FÜR PFADE:
// Zeige hier auf den Ordner, der 'src', 'vendor' etc. enthält.
// ==========================================================
// --- ANKER-SYSTEM ---
// 1. Versuch: Wir sind in /public/ oder /public/api/ und suchen /kga-core/ im Root
$appRoot = dirname(__DIR__, 1) . '/kga-core';

// 2. Versuch: Spezialfall Strato/Ionos (falls /public/ sehr tief verschachtelt ist)
if (!file_exists($appRoot . '/vendor/autoload.php')) {
    $appRoot = dirname(__DIR__, 2) . '/kga-core';
}

// 3. Letzter Rettungsanker: Direkte Suche (nur falls 1 & 2 fehlschlagen)
if (!file_exists($appRoot . '/vendor/autoload.php')) {
    $appRoot = __DIR__ . '/../kga-core';
}
// --------------------

use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;
use App\Contracts\Storage\StorageInterface;

$settings = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$container = new Container(new Config($settings));

/** @var StorageInterface $storage */
$storage = $container->get(StorageInterface::class);
$code = strtoupper(trim($_GET['code'] ?? ''));
$permit = $storage->findByHash($code);

if (!$permit) {
    die("Genehmigung mit dem Code $code nicht gefunden.");
}

// Admin-Prüfung via Token
$token = $_GET['token'] ?? '';
$expectedToken = hash('sha256', $permit->code . $settings['geheimnis']);
$isAdmin = hash_equals($expectedToken, $token);

// Weiche: Welches Template laden?
if ($isAdmin) {
    include $appRoot . '/templates/pages/check_admin.phtml';
} else {
    include $appRoot . '/templates/pages/check_public.phtml';
}
