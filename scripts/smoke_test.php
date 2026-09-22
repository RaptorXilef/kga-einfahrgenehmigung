<?php

declare(strict_types=1);

/**
 * Automatischer Dependency Injection Smoke-Test.
 *
 * Simuliert den Aufruf JEDER registrierten Action und JEDES Handlers im System.
 * Wenn auch nur ein einziger Namespace falsch ist, ein Use-Case fehlt, oder ein
 * Konstruktor-Parameter (Service) nicht aufgelöst werden kann, knallt dieses Script sofort.
 */

use App\Application\Routing\ActionRegistry;
use App\Bootstrap\Container;

// 1. System hochfahren (WICHTIG: Noch KEIN echo davor, da session_start() ausgeführt wird!)
$appRoot = \dirname(__DIR__);
/** @var Container $container */
$container = require_once $appRoot . '/src/Bootstrap/app.php';

// JETZT dürfen wir Text ausgeben:
echo "🔥 Starte Dependency Injection Smoke-Test...\n\n";

// 2. Action Registry auslesen
$registry = $container->get(ActionRegistry::class);
$reflection = new \ReflectionClass($registry);
$routesProperty = $reflection->getProperty('routes');
$routes = $routesProperty->getValue($registry);

$classesToTest = [];

// Alle Actions aus den Routen sammeln
foreach (['exact', 'dynamic'] as $type) {
    foreach ($routes[$type] as $method => $paths) {
        foreach ($paths as $routeData) {
            $classesToTest[$routeData['class']] = true;
        }
    }
}

$successCount = 0;
$errorCount = 0;

// 3. Jede Klasse aus dem Container anfordern!
foreach (\array_keys($classesToTest) as $className) {
    try {
        // Hier passiert die Magie: Der Container versucht den kompletten Abhängigkeitsbaum
        // (Action -> Handler -> Repository -> PDO -> Config) aufzubauen.
        $instance = $container->get($className);
        ++$successCount;
    } catch (\Throwable $e) {
        echo '❌ FEHLER in ' . $className . ":\n";
        echo '   -> ' . $e->getMessage() . "\n\n";
        ++$errorCount;
    }
}

echo "========================================\n";
if ($errorCount === 0) {
    echo "✅ SUCCESS! Alle $successCount Actions und deren Abhängigkeiten sind auflösbar.\n";
    echo "Das Refactoring hat keine offensichtlichen Namespace- oder Konstruktor-Bugs hinterlassen.\n";
} else {
    echo "🚨 FAIL! Es wurden $errorCount fehlerhafte Abhängigkeiten gefunden.\n";
}
echo "========================================\n";
