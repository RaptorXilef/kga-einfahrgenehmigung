<?php

/**
 * API: Liefert den Preis für ein Template und Fahrzeugtyp v0.15.0
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

    return \dirname(__DIR__, 2);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Infrastructure\Config\Config;

\header('Content-Type: application/json');

$key = (string) ($_GET['key'] ?? 'std_7');
$typ = (string) ($_GET['typ'] ?? 'pkw');

$settings              = require $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$config                = new Config($settings);

$templates = $config->get('permit_templates', []);
$template  = $templates[$key] ?? $templates['std_7'];

$price = (float) ($template['prices'][$typ] ?? 0.0);

echo \json_encode(['price' => $price, 'formatted' => \number_format($price, 2, ',', '.') . ' €']);
