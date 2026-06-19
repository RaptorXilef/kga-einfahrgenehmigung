<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;

/**
 * Action zum Rendern der Impressum-Seite.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ImpressumAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * Lädt die statischen Daten aus der Konfiguration und rendert die Impressum-Seite.
     */
    public function execute(array $requestData): mixed
    {
        $root      = $this->config->get('root_path');
        $legalData = include $root . '/config/impressum.php';

        $this->renderer->render('impressum', [
            'legal' => $legalData,
        ]);

        return null;
    }
}
