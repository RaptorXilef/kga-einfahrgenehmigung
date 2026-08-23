<?php

declare(strict_types=1);

// Die app.php startet die Session sicher und mit den korrekten Cookie-Parametern
require_once __DIR__ . '/../../src/Bootstrap/app.php';

// Wir schicken einfach einen winzigen 200 OK Status zurück
\header('Content-Type: application/json');
echo \json_encode(['status' => 'alive', 'time' => \time()]);
exit;
