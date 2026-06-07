<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/HistoryController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class LegalController
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    /**
     * TODO DOCBLOCK
     * Rendert das Impressum
     */
    public function renderImpressum(): void
    {
        // Lädt die dedizierte Config-Datei direkt über das Root-Verzeichnis
        $root      = $this->config->get('root_path');
        $legalData = include $root . '/config/impressum.php';

        $this->render('impressum', [
            'legal'    => $legalData,
            'settings' => $this->getSettingsArray(),
            'appRoot'  => $root,
        ]);
    }

    /**
     * TODO DOCBLOCK
     * Rendert die Datenschutzerklärung
     */
    public function renderDatenschutz(): void
    {
        $root      = $this->config->get('root_path');
        $legalData = include $root . '/config/datenschutz.php';

        $this->render('datenschutz', [
            'legal'    => $legalData,
            'settings' => $this->getSettingsArray(),
            'appRoot'  => $root,
        ]);
    }

    /**
     * TODO DOCBLOCK
     * Holt Basis-Einstellungen für Header/Nav/Favicon analog zum PermitController
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name' => $this->config->get('vereins_name'),
            'base_url'     => $this->config->getBaseUrl(),
            'jahresFarbe'  => $this->config->get('jahresFarbe'),
        ];
    }

    /**
     * TODO DOCBLOCK
     * Rendering-Hilfsmethode
     */
    private function render(string $templatePath, array $data = []): void
    {
        \extract($data);
        include $this->config->get('root_path') . "/templates/pages/{$templatePath}.phtml";
    }
}
