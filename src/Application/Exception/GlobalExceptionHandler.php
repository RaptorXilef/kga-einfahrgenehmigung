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
        // 1. Fehler revisionssicher loggen
        $this->logger->logThrowable($exception);

        // 2. Prüfen, ob wir im Dev-Modus sind (dann wollen wir die echten Fehler sehen!)
        $isDev = (bool) $this->config->get('debug_mode', false);

        // VSA FIX: Entfernung der Superglobal $_SERVER. Ermittlung erfolgt über filter_input und Config.
        $scriptName = (string) $this->config->get('server_script', '');
        $httpAccept = (string) \filter_input(\INPUT_SERVER, 'HTTP_ACCEPT');
        $contentType = (string) \filter_input(\INPUT_SERVER, 'CONTENT_TYPE');

        $isApi = \str_contains($scriptName, '/api/')
            || \str_contains($httpAccept, 'application/json')
            || \str_contains($contentType, 'application/json');

        if ($isApi) {
            $msg = $isDev ? $exception->getMessage() : 'Ein interner Serverfehler ist aufgetreten.';
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
        }

        // Binden wir die PHTML-Datei ein (falls nicht vorhanden -> Ultra Fallback)
        $templatePath = $appRoot . '/templates/pages/frontend/system_error.phtml';
        if (\file_exists($templatePath)) {
            include $templatePath;
        } else {
            echo "<h1>$errorTitle</h1><p>$errorMessage</p>";
            if ($isDev) {
                echo $debugInfo;
            }
        }

        exit;
    }
}
