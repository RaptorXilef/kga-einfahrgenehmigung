<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;

/**
 * Action zum Rendern der Datenschutzerklärung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class DatenschutzAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * Lädt die statischen Daten aus der Konfiguration und rendert die Datenschutzerklärung.
     */
    public function execute(ServerRequest $request): mixed
    {
        $root      = $this->config->get('root_path');
        $legalData = include $root . '/config/datenschutz.php';

        $this->renderer->render('datenschutz', [
            'legal' => $legalData,
        ]);

        return null;
    }
}
