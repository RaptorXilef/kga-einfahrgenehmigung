<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\Utils\ClockInterface;
use Override;
use Throwable;

/**
 * Überwacht globale und feingranulare Wartungsmodi für die Anwendung.
 * Kapselt das Routing-Sicherheitsnetz sauber ab (SRP).
 */
final readonly class MaintenanceModeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private AssetHelperInterface $assetHelper,
        private SystemInfoInterface $systemInfo,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $relativePath = $this->resolveRelativePath($request);

        if ($relativePath === '/maintenance') {
            return $this->sendMaintenanceResponse('Manuelle Wartungsansicht aufgerufen.', $relativePath);
        }

        $status = $this->checkMaintenanceStatus($relativePath);
        if ($status['active']) {
            return $this->sendMaintenanceResponse($status['message'], $relativePath);
        }

        return $next($request);
    }

    /**
     * @return array{active: bool, message: string}
     */
    private function checkMaintenanceStatus(string $relativePath): array
    {
        if ($relativePath === '/admin_login') {
            return ['active' => false, 'message' => ''];
        }

        $mConfig = $this->config->getArray('maintenance');

        $frontendGlobal = (bool) ($mConfig['frontend'] ?? false);
        $adminGlobal = (bool) ($mConfig['admin'] ?? false);
        $apiGlobal = (bool) ($mConfig['api'] ?? false);
        $globalMsg = (string) ($mConfig['message'] ?? 'Wir aktualisieren gerade das System.');
        $routeRules = (array) ($mConfig['routes'] ?? []);

        $isApiRoute = \str_starts_with($relativePath, '/api/');
        $isAdminRoute = \in_array($relativePath, ['/admin', '/users', '/profile', '/changelog', '/admin_logout', '/admin_print'], true);
        $isFrontendRoute = !$isApiRoute && !$isAdminRoute;

        $isAdminLoggedIn = $this->sessionManager->getAdminGroup() === 'admin';

        $isActive = false;
        $message = $globalMsg;

        if (isset($routeRules[$relativePath]) && $routeRules[$relativePath] !== false) {
            $isActive = true;
            if (\is_string($routeRules[$relativePath])) {
                $message = $routeRules[$relativePath];
            }
        }

        if (!$isActive) {
            if ($isApiRoute && $apiGlobal) {
                $isActive = true;
            } elseif ($isAdminRoute && $adminGlobal) {
                $isActive = true;
            } elseif ($isFrontendRoute && $frontendGlobal) {
                $isActive = true;
            }
        }

        // Admin-Bypass fürs Frontend
        if ($isActive && $isFrontendRoute && $isAdminLoggedIn) {
            $isActive = false;
        }

        return ['active' => $isActive, 'message' => $message];
    }

    private function resolveRelativePath(ServerRequest $request): string
    {
        $pathRaw = \parse_url($request->getPath(), \PHP_URL_PATH);
        $path = \is_string($pathRaw) ? $pathRaw : '/';

        $basePathRaw = \parse_url($this->config->getBaseUrl(), \PHP_URL_PATH);
        $basePath = \is_string($basePathRaw) ? $basePathRaw : '/';

        $relativePath = '/' . \ltrim($path, '/');

        if (\str_starts_with($path, $basePath)) {
            $relativePath = '/' . \ltrim(\substr($path, \strlen($basePath)), '/');
        }

        if (\str_ends_with($relativePath, '.php')) {
            $relativePath = \substr($relativePath, 0, -4);
        }

        if ($relativePath === '/index') {
            return '/';
        }

        return $relativePath;
    }

    private function sendMaintenanceResponse(string $message, string $relativePath): ResponseInterface
    {
        if (\str_starts_with($relativePath, '/api/')) {
            return JsonResponse::error($message, 503);
        }

        $rootPath = \rtrim($this->config->getString('root_path'), '/\\');
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';
        $vereinsName = $this->config->getString('vereins_name', 'KGA e.V.');
        $cspNonce = \defined('CSP_NONCE') ? (string) CSP_NONCE : '';

        $logoFile = null;
        foreach (['webp', 'png', 'jpg'] as $ext) {
            $localPath = $rootPath . '/public/assets/img/logo/kga.' . $ext;
            if (\file_exists($localPath)) {
                $logoFile = "assets/img/logo/kga.{$ext}";
                break;
            }
        }

        $headerNavHtml = $this->renderHeaderNavSafely($rootPath, $baseUrl, $vereinsName, $relativePath);
        $footerHtml = $this->renderFooterSafely($rootPath, $baseUrl, $cspNonce);

        $viewVars = [
            'baseUrl' => $baseUrl,
            'vereinsName' => $vereinsName,
            'maintenanceModeAdmin' => (bool) ($this->config->getArray('maintenance')['admin'] ?? false),
            'displayMessage' => $message,
            'logoFile' => $logoFile,
            'headerNavHtml' => $headerNavHtml,
            'footerHtml' => $footerHtml,
            'cspNonce' => $cspNonce,
        ];
        \extract($viewVars);

        \ob_start();
        include $rootPath . '/templates/pages/frontend/maintenance.phtml';
        $html = \ob_get_clean();

        return new HtmlResponse((string) $html, 503);
    }

    /**
     * Rendert die öffentliche Kopfnavigation fehlertolerant.
     * Falls die Template-Datei während eines System-Updates kurzzeitig fehlt oder nicht lesbar ist,
     * wird ein leerer String zurückgegeben, damit die Wartungsseite niemals abstürzt.
     */
    private function renderHeaderNavSafely(
        string $rootPath,
        string $baseUrl,
        string $vereinsName,
        string $relativePath,
    ): string {
        $navPath = $rootPath . '/templates/partials/frontend/public_header_nav.phtml';
        if (!\is_file($navPath) || !\is_readable($navPath)) {
            return '';
        }

        $currentRoute = \trim($relativePath, '/');
        if ($currentRoute === '') {
            $currentRoute = 'index';
        }

        $navVars = [
            'asset' => $this->assetHelper,
            'settings' => ['base_url' => $baseUrl],
            'vereinsName' => $vereinsName,
            'navActiveIndexClass' => $currentRoute === 'index' ? 'is-active' : '',
            'navActiveHistoryClass' => \str_starts_with($currentRoute, 'history') ? 'is-active' : '',
            'navActiveCheckClass' => $currentRoute === 'check' ? 'is-active' : '',
        ];

        $obLevel = \ob_get_level();
        \ob_start();

        try {
            \extract($navVars);
            include $navPath;

            return (string) \ob_get_clean();
        } catch (Throwable) {
            while (\ob_get_level() > $obLevel) {
                \ob_end_clean();
            }

            return '';
        }
    }

    /**
     * Rendert den globalen Footer (inkl. Consent-Banner-Prüfung) fehlertolerant.
     * Falls eine der Dateien während eines Updates fehlt, wird ein leerer String zurückgegeben.
     */
    private function renderFooterSafely(string $rootPath, string $baseUrl, string $cspNonce): string
    {
        $footerPath = $rootPath . '/templates/partials/frontend/footer.phtml';
        $consentPath = $rootPath . '/templates/partials/frontend/consent_banner.phtml';

        if (!\is_file($footerPath) || !\is_readable($footerPath) || !\is_file($consentPath) || !\is_readable($consentPath)) {
            return '';
        }

        $obLevel = \ob_get_level();
        \ob_start();

        try {
            $currentYear = $this->clock->now()->format('Y');
            $startYear = 2026;
            $footerYearDisplay = (int) $currentYear > $startYear ? "{$startYear} - {$currentYear}" : (string) $startYear;

            $adminUserId = $this->sessionManager->getUserId();
            $globalPermissions = $this->sessionManager->getPermissions();
            $isSysAdmin = \str_starts_with($adminUserId, 'sys_');
            $hasGodMode = ($globalPermissions['*'] ?? false) || $isSysAdmin;
            $canAccessAdmin = ($globalPermissions['admin.access'] ?? false) || $hasGodMode;

            $footerVars = [
                'settings' => ['base_url' => $baseUrl],
                'appRoot' => $rootPath,
                'appVersion' => $this->systemInfo->getCurrentVersion(),
                'footerYearDisplay' => $footerYearDisplay,
                'footerSoftwareName' => 'KGA-Einfahrts-Manager',
                'footerIssuesUrl' => 'https://github.com/RaptorXilef/kga-einfahrgenehmigung/issues',
                'footerImpressumUrl' => $baseUrl . 'impressum',
                'footerDatenschutzUrl' => $baseUrl . 'datenschutz',
                'debugMetrics' => null,
                'canAccessAdmin' => $canAccessAdmin,
                'cspNonce' => $cspNonce,
                'consentEnabled' => false,
                'consentConfigJson' => '{}',
                'consentTitle' => '',
                'consentDescription' => '',
                'consentLinkDatenschutz' => 'Datenschutzerklärung',
                'consentLinkImpressum' => 'Impressum',
                'consentAcceptAll' => '',
                'consentAcceptEssential' => '',
                'consentShowDetails' => '',
                'consentSaveSelection' => '',
                'consentGroups' => [],
            ];

            \extract($footerVars);
            include $footerPath;

            return (string) \ob_get_clean();
        } catch (Throwable) {
            while (\ob_get_level() > $obLevel) {
                \ob_end_clean();
            }

            return '';
        }
    }
}
