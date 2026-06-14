<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\View\TemplateRenderer;
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
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * Lädt die statischen Daten aus der Konfiguration und rendert die Datenschutzerklärung.
     */
    public function renderDatenschutz(): void
    {
        $root      = $this->config->get('root_path');
        $legalData = include $root . '/config/datenschutz.php';

        $this->renderer->render('datenschutz', [
            'legal' => $legalData,
        ]);
    }

    /**
     * Lädt die statischen Daten aus der Konfiguration und rendert die Impressum-Seite.
     */
    public function renderImpressum(): void
    {
        // Lädt die dedizierte Config-Datei direkt über das Root-Verzeichnis
        $root      = $this->config->get('root_path');
        $legalData = include $root . '/config/impressum.php';

        $this->renderer->render('impressum', [
            'legal' => $legalData,
        ]);
    }
}
