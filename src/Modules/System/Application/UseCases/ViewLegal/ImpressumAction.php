<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewLegal;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;

#[Route('GET', '/impressum')]
#[Route('POST', '/impressum')]
final readonly class ImpressumAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $legalData = $this->config->get('impressum', []);

        $html = $this->renderer->render('frontend/impressum', [
            'legal' => $legalData,
        ]);

        return new HtmlResponse($html);
    }
}
