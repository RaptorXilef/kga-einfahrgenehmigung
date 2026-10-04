<?php

declare(strict_types=1);

/**
 * Pest Coverage & Mutation Wrapper
 *
 * Erzwingt die Xdebug-Coverage-Umgebungsvariable für den aktuellen Prozess
 * UND alle asynchronen Kind-Prozesse, die von Pest (z.B. beim Mutation-Testing)
 * gestartet werden. Löst das "No coverage report found" Problem unter Windows.
 */

// 1. System-Umgebungsvariable hart setzen (wird an alle Sub-Prozesse vererbt)
\putenv('XDEBUG_MODE=coverage');
$_ENV['XDEBUG_MODE'] = 'coverage';

// 2. Pfad zum Pest-Binary plattformunabhängig ermitteln
$isWindows = \DIRECTORY_SEPARATOR === '\\';
$pestBin = $isWindows ? 'vendor\\bin\\pest.bat' : 'vendor/bin/pest';

// 3. Argumente aus dem Composer-Aufruf durchschleifen
$args = \array_slice($argv, 1);
$command = $pestBin . ' ' . \implode(' ', $args);

echo "\n🚀 Starte Pest mit erzwungenem XDEBUG_MODE=coverage...\n";
echo '> ' . $command . "\n\n";

// 4. Pest ausführen und den exakten Exit-Code an die Konsole zurückgeben
\passthru($command, $exitCode);
exit($exitCode);
