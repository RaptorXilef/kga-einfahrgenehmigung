<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewLegal;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use Override;

#[Route('GET', '/impressum')]
#[Route('POST', '/impressum')]
final readonly class ImpressumAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private TemplateRenderer $renderer,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $legalData = $this->config->getArray('impressum');
        $title = (string) ($legalData['title'] ?? 'Impressum');
        $vereinsName = $this->config->getString('vereins_name', 'KGA');

        $html = $this->renderer->render('frontend/impressum', [
            'legal' => $legalData,
            'pageTitle' => $title . ' - ' . $vereinsName,
        ]);

        return new HtmlResponse($html);
    }
}
