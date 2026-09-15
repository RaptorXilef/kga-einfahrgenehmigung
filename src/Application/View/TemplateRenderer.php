<?php

declare(strict_types=1);

namespace App\Application\View;

use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Contracts\System\JsonHelperInterface;

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
    ) {
    }

    /**
     * Gibt nun das fertige HTML als String zurück, anstatt es mit 'echo' auszugeben!
     */
    public function render(string $templatePath, array $data = []): string
    {
        $appRoot = \rtrim((string) $this->config->get('root_path'), '/\\');

        // 1. Systemvariablen bereitstellen
        $systemVars = [
            'appRoot' => $appRoot,
            'config' => $this->config,
            'imageStorage' => $this->imageStorage,
            'jsonHelper' => $this->jsonHelper,
            'asset' => $this->assetHelper,
            'settings' => $this->getGlobalSettings(),
            'cspNonce' => \defined('CSP_NONCE') ? CSP_NONCE : '',
        ];

        // Lade alle Flashes automatisch in die View-Daten!
        // Nutzt vorhandene Flashes oder holt sie aus der Session
        $data['flashes'] ??= $this->sessionManager->getFlashes();

        \extract($systemVars);
        \extract($data); // OHNE EXTR_SKIP, damit Templates lokale Variablen setzen können!

        // 1. Content in den Puffer rendern
        \ob_start();
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
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
        ];
    }
}
