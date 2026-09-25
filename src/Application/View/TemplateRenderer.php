<?php

declare(strict_types=1);

namespace App\Application\View;

use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\Utils\ClockInterface;

/**
 * Zentraler Service für das Rendering von PHTML-Templates.
 */
final readonly class TemplateRenderer
{
    public function __construct(
        private ConfigInterface $config,
        private ImageStorageInterface $imageStorage,
        private JsonHelperInterface $jsonHelper,
        private SessionManager $sessionManager,
        private AssetHelperInterface $assetHelper,
        private SystemInfoInterface $systemInfo,
        private ClockInterface $clock,
        private ServerRequest $request,
    ) {
    }

    /**
     * Gibt das fertige HTML als String zurück, anstatt es mit 'echo' auszugeben!
     *
     * @param array<string, mixed> $data
     */
    public function render(string $templatePath, array $data = []): string
    {
        $appRoot = \rtrim($this->config->getString('root_path'), '/\\');

        // 1. Sichere Routen-Ermittlung aus dem gekapselten ServerRequest (Kein $_SERVER mehr!)
        $path = \parse_url($this->request->getPath(), \PHP_URL_PATH);
        $path = \trim((string) $path, '/');
        if (\str_ends_with($path, '.php')) {
            $path = \substr($path, 0, -4);
        }
        $currentRoute = $path === '' ? 'index' : $path;

        // 2. Metriken für den Footer vorbereiten (Logik aus PHTML entfernt)
        $isDebugMode = $this->config->getBool('debug_mode', false);
        $debugMetrics = null;
        if ($isDebugMode) {
            $reqTimeRaw = $this->request->server['REQUEST_TIME_FLOAT'] ?? null;
            $requestTime = \is_numeric($reqTimeRaw) ? (float) $reqTimeRaw : (float) (\defined('APP_REQUEST_TIME') ? APP_REQUEST_TIME : \microtime(true));
            $timeMs = \round((\microtime(true) - $requestTime) * 1000, 2);
            $memoryMb = \round(\memory_get_peak_usage() / 1024 / 1024, 2);
            $debugMetrics = ['timeMs' => $timeMs, 'memoryMb' => $memoryMb];
        }

        // Globale Layout-, Test-Mode-, Consent- & Footer-Variablen auflösen (100% logikfreie Partials)
        $adminUserId = $this->sessionManager->getUserId();
        $adminRoleRaw = $this->sessionManager->getAdminGroup();
        $adminRoleName = \ucfirst(\str_replace('role_', '', $adminRoleRaw));
        $adminAvatarUrl = $adminUserId !== '' ? $this->imageStorage->getImageUrl('user', $adminUserId, 'user.webp') : '';

        $globalPermissions = $this->sessionManager->getPermissions();
        $isSysAdmin = \str_starts_with($adminUserId, 'sys_');
        $hasGodMode = ($globalPermissions['*'] ?? false) || $isSysAdmin;
        $canManageSystem = ($globalPermissions['system.manage'] ?? false) || $hasGodMode;
        $canAccessAdmin = ($globalPermissions['admin.access'] ?? false) || $hasGodMode;

        $currentYear = $this->clock->now()->format('Y');
        $startYear = 2026;
        $footerYearDisplay = (int) $currentYear > $startYear ? "{$startYear} - {$currentYear}" : (string) $startYear;
        $safeBaseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';
        $vereinsName = $this->config->getString('vereins_name', 'KGA');
        $vehicleConfigJson = \json_encode(
            $this->config->getArray('vehicle_types'),
            \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT,
        ) ?: '{}';

        $isTestMode = $this->config->isTestMode();
        $mailSettings = $this->config->getMailSettings();
        $testCatchAllRecipient = (string) ($mailSettings['catch_all_recipient'] ?? 'test@example.com');

        $consentConfig = $this->config->getArray('consent');
        $consentTexts = \is_array($consentConfig['texts'] ?? null) ? $consentConfig['texts'] : [];
        $gaCfg = $this->config->getArray('ga4_server_side');
        $gaId = (string) ($gaCfg['measurement_id'] ?? '');

        $consentGroups = [];
        foreach ((array) ($consentConfig['groups'] ?? []) as $group) {
            if (!\is_array($group)) {
                continue;
            }
            $gid = (string) ($group['id'] ?? '');
            $isRequired = (bool) ($group['required'] ?? false);
            $consentGroups[] = [
                'id' => $gid,
                'title' => (string) ($group['title'] ?? ''),
                'description' => (string) ($group['description'] ?? ''),
                'checkboxClass' => $gid === 'analytics' ? 'js-consent-chk-analytics' : '',
                'requiredAttr' => $isRequired ? 'checked disabled' : '',
            ];
        }

        $consentConfigJson = \json_encode([
            'gaId' => $gaId,
            'texts' => $consentTexts,
        ], \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT) ?: '{}';

        // Lade alle Flashes automatisch in die View-Daten und bereite sie logikfrei für alerts.phtml auf!
        $rawFlashes = \is_array($data['flashes'] ?? null) ? $data['flashes'] : $this->sessionManager->getFlashes();
        $data['flashes'] = $rawFlashes;
        $data['flashAlerts'] = $this->prepareFlashAlerts($rawFlashes);

        // 3. Systemvariablen bereitstellen
        $systemVars = [
            'appRoot' => $appRoot,
            'config' => $this->config,
            'imageStorage' => $this->imageStorage,
            'jsonHelper' => $this->jsonHelper,
            'asset' => $this->assetHelper,
            'settings' => $this->getGlobalSettings(),
            'baseUrl' => $safeBaseUrl,
            'vereinsName' => $vereinsName,
            'vehicleConfigJson' => $vehicleConfigJson,
            'logoUrl' => $this->resolveLogoUrl($appRoot),
            'queryParams' => $this->request->get,
            'cspNonce' => \defined('CSP_NONCE') ? CSP_NONCE : '',
            'csrfToken' => $this->sessionManager->getCsrfToken(),
            'currentRoute' => $currentRoute,
            'navActiveUsersClass' => $currentRoute === 'users' ? 'is-active' : '',
            'navActiveAdminClass' => $currentRoute === 'admin' ? 'is-active' : '',
            'navActiveIndexClass' => $currentRoute === 'index' ? 'is-active' : '',
            'navActiveHistoryClass' => $currentRoute === 'history' ? 'is-active' : '',
            'navActiveCheckClass' => $currentRoute === 'check' ? 'is-active' : '',
            'appVersion' => $this->systemInfo->getCurrentVersion(),
            'currentYear' => $currentYear,
            'footerYearDisplay' => $footerYearDisplay,
            'footerSoftwareName' => 'KGA-Einfahrts-Manager',
            'footerIssuesUrl' => 'https://github.com/RaptorXilef/kga-einfahrgenehmigung/issues',
            'footerImpressumUrl' => $safeBaseUrl . 'impressum',
            'footerDatenschutzUrl' => $safeBaseUrl . 'datenschutz',
            'debugMetrics' => $debugMetrics,
            'isTestMode' => $isTestMode,
            'isDebugMode' => $isDebugMode,
            'testCatchAllRecipient' => $testCatchAllRecipient,
            'consentEnabled' => (bool) ($consentConfig['enabled'] ?? false),
            'consentConfigJson' => $consentConfigJson,
            'consentTitle' => (string) ($consentTexts['title'] ?? ''),
            'consentDescription' => (string) ($consentTexts['description'] ?? ''),
            'consentLinkDatenschutz' => (string) ($consentTexts['link_datenschutz'] ?? 'Datenschutzerklärung'),
            'consentLinkImpressum' => (string) ($consentTexts['link_impressum'] ?? 'Impressum'),
            'consentAcceptAll' => (string) ($consentTexts['accept_all'] ?? ''),
            'consentAcceptEssential' => (string) ($consentTexts['accept_essential'] ?? ''),
            'consentShowDetails' => (string) ($consentTexts['show_details'] ?? ''),
            'consentSaveSelection' => (string) ($consentTexts['save_selection'] ?? ''),
            'consentGroups' => $consentGroups,
            // Globale Admin Layout Variablen
            'adminUserId' => $adminUserId,
            'adminUserName' => $this->sessionManager->getAdminUser(),
            'adminRoleName' => $adminRoleName,
            'adminAvatarUrl' => $adminAvatarUrl,
            'globalPermissions' => $globalPermissions,
            'hasGodMode' => $hasGodMode,
            'canManageSystem' => $canManageSystem,
            'canAccessAdmin' => $canAccessAdmin,
            'allReleaseNotes' => $this->systemInfo->getAllReleaseNotes(),
        ];

        \extract($systemVars);
        \extract($data); // OHNE EXTR_SKIP, damit Templates lokale Variablen setzen können!

        // Intelligente Pfad-Auflösung
        $fullPath = $appRoot . "/templates/pages/{$templatePath}.phtml";
        if (!\file_exists($fullPath)) {
            // Fallback auf den Basis-Ordner (Wichtig für /emails/ und /partials/)
            $fullPath = $appRoot . "/templates/{$templatePath}.phtml";
        }

        // 1. Content in den Puffer rendern
        \ob_start();
        include $fullPath;
        $content = \ob_get_clean();

        // 2. Layout Rendern (inkl. aufgelöster Defaults für pageTitle & pageStateClass)
        if (isset($layout) && \is_string($layout) && \file_exists($appRoot . "/templates/layouts/{$layout}.phtml")) {
            $defaultTitle = $layout === 'admin' ? 'Admin - ' . $vereinsName : $vereinsName;
            $pageTitle = isset($pageTitle) && \is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : $defaultTitle;
            $pageStateClass = isset($pageStateClass) && \is_string($pageStateClass) ? $pageStateClass : '';

            \ob_start();
            include $appRoot . "/templates/layouts/{$layout}.phtml";

            return \ob_get_clean() ?: '';
        }

        return $content ?: '';
    }

    /**
     * Bereitet die rohen Flash-Messages aus der Session inkl. BEM-Modifier und HTML-Sanitizing für alerts.phtml auf.
     *
     * @param array<string, mixed> $rawFlashes
     *
     * @return array<int, array{modifier: string, html: string}>
     */
    private function prepareFlashAlerts(array $rawFlashes): array
    {
        $alerts = [];
        $allowedTags = '<div><span><strong><em><b><i><br><ul><li><img>';

        foreach ($rawFlashes as $type => $messages) {
            if (!\is_array($messages)) {
                continue;
            }

            $modifier = match ((string) $type) {
                'error' => 'danger',
                'success' => 'success',
                'warning' => 'warning',
                default => 'info',
            };

            foreach ($messages as $msg) {
                $alerts[] = [
                    'modifier' => $modifier,
                    'html' => \strip_tags((string) $msg, $allowedTags),
                ];
            }
        }

        return $alerts;
    }

    private function resolveLogoUrl(string $appRoot): ?string
    {
        foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
            $serverPath = $appRoot . '/public/assets/img/logo/kga.' . $ext;
            if (\file_exists($serverPath)) {
                return $this->assetHelper->url('assets/img/logo/kga.' . $ext);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getGlobalSettings(): array
    {
        $templates = (array) $this->config->get('permit_templates', []);

        return [
            'base_url' => \rtrim($this->config->getBaseUrl(), '/') . '/',
            'bic' => $this->config->get('bic'),
            'iban' => $this->config->get('iban'),
            'jahresFarbe' => $this->config->get('jahresFarbe'),
            'kontoinhaber' => $this->config->get('kontoinhaber'),
            'opening_hours' => $this->config->get('default_opening_hours'),
            'public_templates' => \array_filter($templates, fn (array $t): bool => ($t['public'] ?? false) === true),
            'purposes' => $this->config->get('purposes'),
            'terminkalender_url' => $this->config->get('terminkalender_url'),
            'vehicle_types' => $this->config->get('vehicle_types'),
            'vereins_name' => $this->config->getString('vereins_name', 'KGA'),
            'debug_mode' => $this->config->get('debug_mode', false),
            'consent' => $this->config->getArray('consent'),
            'ga4_server_side' => $this->config->getArray('ga4_server_side'),
        ];
    }
}
