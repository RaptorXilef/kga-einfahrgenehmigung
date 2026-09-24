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
     * Gibt nun das fertige HTML als String zurück, anstatt es mit 'echo' auszugeben!
     */
    public function render(string $templatePath, array $data = []): string
    {
        $appRoot = \rtrim((string) $this->config->get('root_path'), '/\\');

        // 1. Sichere Routen-Ermittlung aus dem gekapselten ServerRequest
        $requestUri = (string) ($this->request->server['REQUEST_URI'] ?? '/');
        $path = \parse_url($requestUri, \PHP_URL_PATH);
        $path = \trim((string) $path, '/');
        if (\str_ends_with($path, '.php')) {
            $path = \substr($path, 0, -4);
        }
        $currentRoute = $path === '' ? 'index' : $path;

        // 2. Metriken für den Footer vorbereiten (Logik aus PHTML entfernt)
        $debugMetrics = null;
        if ($this->config->get('debug_mode', false)) {
            $reqTimeRaw = $this->request->server['REQUEST_TIME_FLOAT'] ?? null;
            $requestTime = \is_numeric($reqTimeRaw) ? (float) $reqTimeRaw : (float) (\defined('APP_REQUEST_TIME') ? APP_REQUEST_TIME : \microtime(true));
            $timeMs = \round((\microtime(true) - $requestTime) * 1000, 2);
            $memoryMb = \round(\memory_get_peak_usage() / 1024 / 1024, 2);
            $debugMetrics = ['timeMs' => $timeMs, 'memoryMb' => $memoryMb];
        }

        // VSA FIX: Globale Layout-Variablen auflösen, um HeaderNav & Footer logikfrei zu machen
        $adminUserId = $this->sessionManager->getUserId();
        $adminRoleRaw = $this->sessionManager->getAdminGroup();
        $adminRoleName = \ucfirst(\str_replace('role_', '', $adminRoleRaw));
        $adminAvatarUrl = $adminUserId !== '' ? $this->imageStorage->getImageUrl('user', $adminUserId, 'user.webp') : '';

        // 3. Systemvariablen bereitstellen
        $systemVars = [
            'appRoot' => $appRoot,
            'config' => $this->config,
            'imageStorage' => $this->imageStorage,
            'jsonHelper' => $this->jsonHelper,
            'asset' => $this->assetHelper,
            'settings' => $this->getGlobalSettings(),
            'cspNonce' => \defined('CSP_NONCE') ? CSP_NONCE : '',
            'csrfToken' => $this->sessionManager->getCsrfToken(),
            'currentRoute' => $currentRoute,
            'appVersion' => $this->systemInfo->getCurrentVersion(),
            'currentYear' => $this->clock->now()->format('Y'),
            'debugMetrics' => $debugMetrics,
            // Globale Admin Layout Variablen
            'adminUserId' => $adminUserId,
            'adminUserName' => $this->sessionManager->getAdminUser(),
            'adminRoleName' => $adminRoleName,
            'adminAvatarUrl' => $adminAvatarUrl,
            'globalPermissions' => $this->sessionManager->getPermissions(),
            'allReleaseNotes' => $this->systemInfo->getAllReleaseNotes(),
        ];

        // Lade alle Flashes automatisch in die View-Daten!
        // Nutzt vorhandene Flashes oder holt sie aus der Session
        $data['flashes'] ??= $this->sessionManager->getFlashes();

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

        // 2. Layout Rendern
        if (isset($layout) && \is_string($layout) && \file_exists($appRoot . "/templates/layouts/{$layout}.phtml")) {
            \ob_start();
            include $appRoot . "/templates/layouts/{$layout}.phtml";

            return \ob_get_clean() ?: '';
        }

        return $content ?: '';
    }

    private function getGlobalSettings(): array
    {
        $templates = (array) $this->config->get('permit_templates', []);

        return [
            // FALLBACK FÜR KGA-TEMPLATES: Wir erzwingen hier den Slash am Ende!
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
            'vereins_name' => $this->config->get('vereins_name'),
            'debug_mode' => $this->config->get('debug_mode', false), // Wird vom Footer für Metriken genutzt
        ];
    }
}
