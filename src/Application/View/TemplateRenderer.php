<?php

declare(strict_types=1);

namespace App\Application\View;

use App\Contracts\Config\ConfigInterface;

/**
 * TODO DOCBLOCK
 * Zentraler Service für das Rendering von PHTML-Templates.
 * Sammelt globale System-Variablen und injiziert sie sicher in den View-Scope.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class TemplateRenderer
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    // TODO DOCBLOCK
    public function render(string $templatePath, array $data = []): void
    {
        $appRoot = \rtrim((string) $this->config->get('root_path'), '/\\');

        $templateData = \array_merge([
            'appRoot'  => $appRoot,
            'config'   => $this->config,
            'settings' => $this->getGlobalSettings(),
        ], $data);

        \extract($templateData, \EXTR_SKIP);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }

    // TODO DOCBLOCK
    private function getGlobalSettings(): array
    {
        $templates = (array) $this->config->get('permit_templates', []);

        return [
            'base_url'           => $this->config->getBaseUrl(),
            'jahresFarbe'        => $this->config->get('jahresFarbe'),
            'opening_hours'      => $this->config->get('default_opening_hours'),
            'public_templates'   => \array_filter($templates, fn (array $t): bool => ($t['public'] ?? false) === true),
            'purposes'           => $this->config->get('purposes'),
            'terminkalender_url' => $this->config->get('terminkalender_url'),
            'vehicle_types'      => $this->config->get('vehicle_types'),
            'vereins_name'       => $this->config->get('vereins_name'),
            'iban'               => $this->config->get('iban'),
            'kontoinhaber'       => $this->config->get('kontoinhaber'),
            'bic'                => $this->config->get('bic'),
        ];
    }
}
