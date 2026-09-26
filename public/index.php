<?php

declare(strict_types=1);

use App\Application\FrontendController;
use App\Application\Http\ServerRequest;
use App\Bootstrap\Container;
use Throwable;

try {
    $bootstrapPath = __DIR__ . '/../src/Bootstrap/app.php';
    if (!\is_file($bootstrapPath) || !\is_readable($bootstrapPath)) {
        throw new \RuntimeException('Bootstrap file temporarily unavailable during update.');
    }

    $container = require_once $bootstrapPath;
    \assert($container instanceof Container);

    // ServerRequest muss auf das neue Format aus TwoKinds (mit Cookies) reagieren.
    // Falls deine Klasse Cookies noch nicht unterstützt, können wir das gleich nachrüsten.
    $req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER, [], $_COOKIE ?? []);

    // VSA FIX: Binde den Request in den Container, damit Middlewares und der TemplateRenderer
    // ihn via Dependency Injection erhalten können, ohne auf $_SERVER zugreifen zu müssen!
    $container->bind(ServerRequest::class, fn (): ServerRequest => $req);

    $controller = $container->get(FrontendController::class);

    $response = $controller->handleRequest($req);
    $response->send();
} catch (Throwable) {
    while (\ob_get_level() > 0) {
        \ob_end_clean();
    }

    $requestUri = (string) \filter_input(\INPUT_SERVER, 'REQUEST_URI');
    $httpAccept = (string) \filter_input(\INPUT_SERVER, 'HTTP_ACCEPT');

    if (\str_contains($requestUri, '/api/') || \str_contains($httpAccept, 'application/json')) {
        if (!\headers_sent()) {
            \http_response_code(503);
            \header('Content-Type: application/json; charset=utf-8');
            \header('Retry-After: 120');
        }
        echo '{"success":false,"error":"Das System wird gerade aktualisiert. Bitte versuchen Sie es in Kürze erneut."}';
        exit;
    }

    $maintenanceScript = __DIR__ . '/maintenance.php';
    if (\is_file($maintenanceScript) && \is_readable($maintenanceScript)) {
        try {
            require $maintenanceScript;
            exit;
        } catch (Throwable) {
            while (\ob_get_level() > 0) {
                \ob_end_clean();
            }
        }
    }

    if (!\headers_sent()) {
        \http_response_code(503);
        \header('Content-Type: text/html; charset=utf-8');
        \header('Retry-After: 120');
    }

    echo <<<'HTML'
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Wartungsarbeiten</title>
            <style>
                :root { color-scheme: light dark; }
                body { margin: 0; padding: 1.5rem; font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; box-sizing: border-box; }
                @media (prefers-color-scheme: dark) { body { background: #0f172a; color: #f1f5f9; } .card { background: #1e293b !important; border-color: #334155 !important; } .muted { color: #94a3b8 !important; } }
                .card { max-width: 34rem; width: 100%; background: #ffffff; border: 1px solid #e2e8f0; border-top: 5px solid #f59e0b; border-radius: 12px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); }
                h1 { margin: 0.75rem 0; font-size: 1.75rem; }
                p { line-height: 1.6; font-size: 1.05rem; margin: 0.75rem 0; }
                .muted { color: #64748b; font-size: 0.9rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(148,163,184,0.25); }
            </style>
        </head>
        <body>
            <main class="card">
                <div style="font-size: 3rem; line-height: 1;">🛠️</div>
                <h1>Kurze Pause!</h1>
                <p>Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.</p>
                <p><strong>In Kürze sind wir wieder für Sie da.</strong></p>
                <div class="muted">Vielen Dank für Ihr Verständnis.</div>
            </main>
        </body>
        </html>
        HTML;
    exit;
}
