<?php

declare(strict_types=1);

namespace App\Application\Exception;

use App\Application\Middleware\MaintenanceModeMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ErrorLoggerInterface;
use Error;
use ErrorException;
use Throwable;

/**
 * Zentraler Exception Handler für die Anwendung.
 *
 * Fängt ungeprüfte Ausnahmen sowie klassische PHP-Fehler ab, loggt diese
 * revisionssicher und gibt eine nutzerfreundliche HTML- oder JSON-Fehlerseite zurück.
 * Erkennt fehlende Abhängigkeiten während Datei-Uploads und zeigt in diesem Fall
 * eine abhängigkeitsfreie Wartungsseite (HTTP 503) statt eines HTTP-500-Fehlers.
 */
final readonly class GlobalExceptionHandler
{
    public function __construct(
        private ConfigInterface $config,
        private ErrorLoggerInterface $logger,
    ) {
        // Preload ins Memory, falls Exception während eines Datei-Updates auftritt
        \class_exists(JsonResponse::class);
        \class_exists(HtmlResponse::class);
        \class_exists(MaintenanceModeMiddleware::class);
    }

    /**
     * Klinkt den Handler global in den PHP-Lebenszyklus ein.
     * Verwandelt auch klassische PHP-Warnungen und -Fehler in fangbare Exceptions.
     */
    public function register(): void
    {
        \set_exception_handler($this->handleException(...));

        // Verwandelt auch klassische PHP-Warnungen/Fehler in Exceptions, damit sie geloggt werden
        \set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if ((\error_reporting() & $errno) === 0) {
                return false;
            }

            // Ignoriere harmlose Deprecation-Warnungen (z.B. von Fremd-Bibliotheken wie league/csv).
            // Verhindert, dass das System abstürzt, nur weil ein Vendor-Paket eine Methode als veraltet markiert!
            if (\in_array($errno, [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
                return false;
            }

            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }

    /**
     * Zentraler Auffangkorb für alle Exceptions und fatalen Fehler.
     *
     * @param Throwable $exception Die geworfene Ausnahme.
     */
    public function handleException(Throwable $exception): void
    {
        // 1. Fehler revisionssicher loggen (fehlertolerant, falls Log-Infrastruktur im Upload ist)
        try {
            $this->logger->logThrowable($exception);
        } catch (Throwable) {
            // Ignorieren, falls während eines Datei-Uploads das Dateisystem blockiert ist
        }

        // 2. Prüfen, ob wir im Dev-Modus sind (dann wollen wir die echten Fehler sehen!)
        $isDev = $this->config->getBool('debug_mode', false);

        // VSA FIX: Entfernung der Superglobal $_SERVER. Ermittlung erfolgt über filter_input und Config.
        $scriptName = $this->config->getString('server_script');
        $httpAccept = (string) \filter_input(\INPUT_SERVER, 'HTTP_ACCEPT');
        $contentType = (string) \filter_input(\INPUT_SERVER, 'CONTENT_TYPE');

        $isApi = \str_contains($scriptName, '/api/')
            || \str_contains($httpAccept, 'application/json')
            || \str_contains($contentType, 'application/json');

        // 3. Wenn der Wartungsmodus aktiv ist ODER während eines Datei-Uploads eine Klasse/Datei fehlt:
        //    Liefere die abhängigkeitsfreie Wartungsseite (HTTP 503) statt eines HTTP-500-Fehlers aus!
        if (!$isDev && $this->shouldServeMaintenanceFallback($exception)) {
            $mConfig = $this->config->getArray('maintenance');
            $msg = (string) ($mConfig['message'] ?? 'Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.');

            if ($isApi) {
                JsonResponse::error($msg, 503)->send();
            }

            $vereinsName = $this->config->getString('vereins_name', 'KGA e.V.');
            $html = MaintenanceModeMiddleware::renderZeroDependencyHtml($vereinsName, $msg);
            (new HtmlResponse($html, 503))->send();
        }

        if ($isApi) {
            $msg = $isDev ? $exception->getMessage() : 'Ein interner Serverfehler ist aufgetreten.';
            JsonResponse::error($msg, 500)->send();
        }

        // HTML-Fehlerseite für normale Browser-Nutzer
        $this->renderErrorPage($exception, $isDev);
    }

    /**
     * Erkennt, ob der Wartungsmodus konfiguriert ist oder ein typischer Upload-/Deployment-Fehler
     * (fehlende Klasse, fehlendes Interface, unvollständige Datei beim Upload) vorliegt.
     */
    private function shouldServeMaintenanceFallback(Throwable $exception): bool
    {
        $mConfig = $this->config->getArray('maintenance');
        if (
            (bool) ($mConfig['frontend'] ?? false)
            || (bool) ($mConfig['admin'] ?? false)
            || (bool) ($mConfig['api'] ?? false)
            || (array) ($mConfig['routes'] ?? []) !== []
        ) {
            return true;
        }

        if ($exception instanceof Error) {
            $msg = $exception->getMessage();
            if (
                \str_contains($msg, 'not found')
                || \str_contains($msg, 'Failed opening required')
                || \str_contains($msg, 'No such file or directory')
                || \str_contains($msg, 'syntax error')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bereitet die Fehlermeldungen vor und inkludiert das PHTML-Template.
     *
     * @param Throwable $exception Die aufgetretene Ausnahme.
     * @param bool $isDev Gibt an, ob der Stacktrace (Dev-Mode) angezeigt werden darf.
     */
    private function renderErrorPage(Throwable $exception, bool $isDev): void
    {
        $appRoot = \rtrim($this->config->getString('root_path'), '/\\');

        $errorTitle = 'Ups! Etwas ist schiefgelaufen';
        $errorMessage = 'Das System hat einen unerwarteten Fehler festgestellt. Keine Sorge, die Administratoren wurden automatisch benachrichtigt um das Problem zu beheben.';
        $debugInfo = '';

        if ($isDev) {
            $errorTitle = \sprintf('Dev-Mode: %s', $exception::class);
            $debugInfo = \sprintf(
                "<strong>Fehler:</strong> %s<br><br><strong>Datei:</strong> %s:%d<br><br><strong>Stacktrace:</strong><pre class='c-system-error__pre'>%s</pre>",
                \htmlspecialchars($exception->getMessage()),
                \htmlspecialchars($exception->getFile()),
                $exception->getLine(),
                \htmlspecialchars($exception->getTraceAsString()),
            );
        }

        // Binden wir die PHTML-Datei ein (falls nicht vorhanden -> Ultra Fallback)
        $templatePath = $appRoot . '/templates/pages/frontend/system_error.phtml';
        if (\file_exists($templatePath)) {
            $viewVars = [
                'vereinsName' => \htmlspecialchars($this->config->getString('vereins_name', 'KGA')),
                'baseUrl' => \rtrim($this->config->getBaseUrl(), '/') . '/',
                'errorTitle' => $errorTitle,
                'errorMessage' => $errorMessage,
                'debugInfo' => $debugInfo,
                'requestState' => '',
            ];
            \extract($viewVars);

            \ob_start();
            include $templatePath;
            $html = \ob_get_clean();
        } else {
            $html = "<h1>$errorTitle</h1><p>$errorMessage</p>";
            if ($isDev) {
                $html .= $debugInfo;
            }
        }

        $response = new HtmlResponse((string) $html, 500);
        $response->send();
    }
}
