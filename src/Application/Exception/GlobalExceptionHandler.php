<?php
declare(strict_types=1);

namespace App\Application\Exception;

use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Logging\ErrorLogger;

// TODO DOCBLOCK
final readonly class GlobalExceptionHandler
{
    public function __construct(
        private ErrorLogger $logger,
        private ConfigInterface $config,
    ) {
    }

    // TODO DOCBLOCK
    /**
     * Klinkt den Handler in PHP ein
     */
    public function register(): void
    {
        \set_exception_handler([$this, 'handleException']);

        // Verwandelt auch klassische PHP-Warnungen/Fehler in Exceptions, damit sie geloggt werden
        \set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if (! (\error_reporting() & $errno)) {
                return false;
            }

            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }

    // TODO DOCBLOCK
    /**
     * Zentraler Auffangkorb für alle Fehler
     */
    public function handleException(\Throwable $exception): void
    {
        // 1. Fehler revisionssicher loggen
        $this->logger->logThrowable($exception);

        // 2. Prüfen, ob wir im Dev-Modus sind (dann wollen wir die echten Fehler sehen!)
        $isDev = (bool) $this->config->get('admin_dev_mode', false);

        // 3. Unterscheiden: War es ein API-Request oder ein normaler Seitenaufruf?
        $isApi = \str_contains($_SERVER['REQUEST_METHOD'] ?? '', 'POST')
            || \str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')
            || (isset($_SERVER['HTTP_ACCEPT']) && \str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isApi) {
            $msg = $isDev ? $exception->getMessage() : 'Ein interner Serverfehler ist aufgetreten.';
            JsonResponse::error($msg, 500);
        }

        // HTML-Fehlerseite für normale Browser-Nutzer
        $this->renderErrorPage($exception, $isDev);
    }

    // TODO DOCBLOCK
    private function renderErrorPage(\Throwable $exception, bool $isDev): void
    {
        \http_response_code(500);
        $vereinsName = \htmlspecialchars((string) $this->config->get('vereins_name', 'KGA'));

        $errorTitle   = 'Ups! Etwas ist schiefgelaufen';
        $errorMessage = 'Das System hat einen unerwarteten Fehler festgestellt. Keine Sorge, die Administratoren wurden automatisch benachrichtigt um das Problem zu beheben.';

        if ($isDev) {
            $errorTitle   = \sprintf('Dev-Mode: %s', \get_class($exception));
            $errorMessage = \sprintf(
                "<strong>Fehler:</strong> %s<br><br><strong>Datei:</strong> %s:%d<br><br><strong>Stacktrace:</strong><pre style='background:#f4f4f4; padding:10px; overflow:auto;'>%s</pre>",
                \htmlspecialchars($exception->getMessage()),
                \htmlspecialchars($exception->getFile()),
                $exception->getLine(),
                \htmlspecialchars($exception->getTraceAsString()),
            );
        }

        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Systemfehler - <?php echo $vereinsName; ?></title>
            <style>
                body { background: #f8fafc; font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; color: #334155; }
                .error-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width: 600px; width: 100%; border-top: 5px solid #ef4444; }
                h1 { color: #dc2626; margin-top: 0; font-size: 1.5rem; }
                p { line-height: 1.6; color: #64748b; }
                .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
                .btn:hover { background: #2563eb; }
            </style>
        </head>
        <body>
            <div class="error-card">
                <h1>🛑 <?php echo $errorTitle; ?></h1>
                <p><?php echo $errorMessage; ?></p>
                <a href="index.php" class="btn">Zur Startseite</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
