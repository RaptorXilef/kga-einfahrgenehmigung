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

$code = $_GET['code'] ?? '';
$token = $_GET['token'] ?? ''; // Admin-Token

$permit = $permitService->getPermitByCode($code); // Neue Methode im Service

if (!$permit) {
    die("Genehmigung nicht gefunden.");
}

// Prüfung: Ist es ein Admin?
$isAdmin = false;
if (!empty($token)) {
    $expectedToken = hash('sha256', $permit->code . $settings['geheimnis']);
    $isAdmin = hash_equals($expectedToken, $token);
}

if ($isAdmin) {
    include $appRoot . '/templates/pages/check_admin.phtml';
} else {
    include $appRoot . '/templates/pages/check_public.phtml';
}
