<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0
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
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$container             = new Container(new Config($settings));

/** @var PermitService $permitService */
$permitService = $container->get(PermitService::class);

$token  = $_GET['token'] ?? '';
$permit = $permitService->confirmEmail((string) $token);

if ($permit) {
    // Erfolg: Weiterleitung zur Check-Seite mit Erfolgsmeldung (grüner Banner)
    \header('Location: check.php?code=' . $permit->code . '&verified=1');
    exit;
}

exit('Bestätigungslink ungültig oder bereits abgelaufen.');
