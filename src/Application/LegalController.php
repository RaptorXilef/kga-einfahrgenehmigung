<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;

/**
 * Controller für rechtliche Informationsseiten (Impressum, Datenschutz).
 *
 * Path: src/Application/LegalController.php
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
     * Lädt die statischen Daten aus der Konfiguration und rendert die Impressum-Seite.
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
     * Lädt die statischen Daten aus der Konfiguration und rendert die Datenschutzerklärung.
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
     * Holt Basis-Einstellungen für Header, Navigation und Favicons.
     *
     * @return array<string, mixed>
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
     * Rendering-Hilfsmethode für die Legal-Templates.
     *
     * @param string               $templatePath Relativer Pfad zum .phtml Template.
     * @param array<string, mixed> $data         Injektionsvariablen.
     */
    private function render(string $templatePath, array $data = []): void
    {
        \extract($data);
        include $this->config->get('root_path') . "/templates/pages/{$templatePath}.phtml";
    }
}
