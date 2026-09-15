<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;

/**
 * Action zum Rendern der Datenschutzerklärung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/datenschutz')]
#[Route('POST', '/datenschutz')]
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
        $legalData = $this->config->get('datenschutz', []);

        $html = $this->renderer->render('frontend/datenschutz', [
            'legal' => $legalData,
        ]);

        return new HtmlResponse($html);
    }
}
