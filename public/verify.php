<?php

declare(strict_types=1);
$appRoot = (function (): void { /* Anker Logik wie gehabt */ })();
require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$container             = new Container(new Config($settings));

/** @var PermitService $permitService */
$permitService = $container->get(PermitService::class);
$token         = $_GET['token'] ?? '';

$permit = $permitService->confirmEmail($token);

if ($permit) {
    // Erfolg! Weiterleitung zur Check-Seite mit Erfolgsmeldung
    \header('Location: check.php?code=' . $permit->code . '&verified=1');
    exit;
}
exit('Ungültiger oder abgelaufener Bestätigungslink.');
