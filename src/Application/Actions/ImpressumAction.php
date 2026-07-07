<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;

/**
 * Action zum Rendern der Impressum-Seite.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('impressum')]
final readonly class ImpressumAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * Lädt die statischen Daten aus der Konfiguration und rendert die Impressum-Seite.
     */
    public function execute(ServerRequest $request): mixed
    {
        $path      = $this->config->getStoragePath('settings/impressum.json');
        $legalData = \file_exists($path) ? $this->jsonHelper->read($path) : [];

        $this->renderer->render('impressum', [
            'legal' => $legalData,
        ]);

        return null;
    }
}
