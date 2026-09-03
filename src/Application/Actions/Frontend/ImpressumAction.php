<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;

/**
 * Action zum Rendern der Impressum-Seite.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/impressum')]
#[Route('POST', '/impressum')]
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
    public function execute(ServerRequest $request): mixed
    {
        // FIX: Daten direkt aus den geladenen Config-Arrays beziehen, nicht über JSON!
        $legalData = $this->config->get('impressum', []);

        $this->renderer->render('frontend/impressum', [
            'legal' => $legalData,
        ]);

        return null;
    }
}
