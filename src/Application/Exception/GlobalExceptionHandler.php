<?php

declare(strict_types=1);

namespace App\Application\Exception;

use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ErrorLoggerInterface;
use ErrorException;
use Throwable;

/**
 * Zentraler Exception Handler für die Anwendung.
 *
 * Fängt ungeprüfte Ausnahmen sowie klassische PHP-Fehler ab, loggt diese
 * revisionssicher und gibt eine nutzerfreundliche HTML- oder JSON-Fehlerseite zurück.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GlobalExceptionHandler
{
    public function __construct(
        private ConfigInterface $config,
        private ErrorLoggerInterface $logger,
    ) {
        // Preload ins Memory, falls Exception während eines Datei-Updates auftritt
        \class_exists(JsonResponse::class);
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
            if (!(\error_reporting() & $errno)) {
                return false;
            }

            // NEU: Ignoriere harmlose Deprecation-Warnungen (z.B. von Fremd-Bibliotheken wie league/csv).
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
        // 1. Fehler revisionssicher loggen
        $this->logger->logThrowable($exception);

        // 2. Prüfen, ob wir im Dev-Modus sind (dann wollen wir die echten Fehler sehen!)
        $isDev = (bool) $this->config->get('debug_mode', false);

        // FIX: Nur ECHTE API-Calls (JSON Accept/Content-Type oder /api/ Route) als JSON beantworten, keine normalen HTML-Formulare!
        $isApi = \str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')
            || (isset($_SERVER['HTTP_ACCEPT']) && \str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && \str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

        if ($isApi) {
            $msg = $isDev ? $exception->getMessage() : 'Ein interner Serverfehler ist aufgetreten.';
            // FIX: Senden erzwingen, um HTML-Rückgaben im API-Layer abzublocken
            JsonResponse::error($msg, 500)->send();
        }

        // HTML-Fehlerseite für normale Browser-Nutzer
        $this->renderErrorPage($exception, $isDev);
    }

    /**
     * Bereitet die Fehlermeldungen vor und inkludiert das PHTML-Template.
     *
     * @param Throwable $exception Die aufgetretene Ausnahme.
     * @param bool $isDev Gibt an, ob der Stacktrace (Dev-Mode) angezeigt werden darf.
     */
    private function renderErrorPage(Throwable $exception, bool $isDev): void
    {
        if (!\headers_sent()) {
            @\http_response_code(500);
        }

        $vereinsName = \htmlspecialchars((string) $this->config->get('vereins_name', 'KGA'));
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';
        $appRoot = \rtrim((string) $this->config->get('root_path', ''), '/\\');

        $errorTitle = 'Ups! Etwas ist schiefgelaufen';
        $errorMessage = 'Das System hat einen unerwarteten Fehler festgestellt. Keine Sorge, die Administratoren wurden automatisch benachrichtigt um das Problem zu beheben.';
        $debugInfo = '';
        $requestState = '';

        // TODO Inline HTML besser lösen!
        if ($isDev) {
            $errorTitle = \sprintf('Dev-Mode: %s', $exception::class);
            $debugInfo = \sprintf(
                "<strong>Fehler:</strong> %s<br><br><strong>Datei:</strong> %s:%d<br><br><strong>Stacktrace:</strong><pre class='c-system-error__pre'>%s</pre>",
                \htmlspecialchars($exception->getMessage()),
                \htmlspecialchars($exception->getFile()),
                $exception->getLine(),
                \htmlspecialchars($exception->getTraceAsString()),
            );

            // Kompletter State Snapshot für maximalen Debug-Komfort
            $stateHtml = "<strong>GET Parameter:</strong>\n" . \htmlspecialchars(\print_r($_GET, true)) . "\n";
            $stateHtml .= "<strong>POST Parameter:</strong>\n" . \htmlspecialchars(\print_r($_POST, true)) . "\n";
            $sessionData = $_SESSION ?? [];
            $stateHtml .= "<strong>SESSION State:</strong>\n" . \htmlspecialchars(\print_r($sessionData, true));

            $requestState = "<div class='c-system-error__debug u-text-start u-margin-block-start-m'><h3 class='u-margin-block-none'>Request State:</h3><pre class='c-system-error__pre'>{$stateHtml}</pre></div>";
        }

        // Binden wir die PHTML-Datei ein (falls nicht vorhanden -> Ultra Fallback)
        $templatePath = $appRoot . '/templates/pages/frontend/system_error.phtml';
        if (\file_exists($templatePath)) {
            include $templatePath;
        } else {
            echo "<h1>$errorTitle</h1><p>$errorMessage</p>";
            if ($isDev) {
                echo $debugInfo;
                echo $requestState;
            }
        }

        exit;
    }
}
