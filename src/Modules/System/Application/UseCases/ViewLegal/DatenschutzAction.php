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

#[Route('GET', '/datenschutz')]
#[Route('POST', '/datenschutz')]
final readonly class DatenschutzAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private TemplateRenderer $renderer,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $legalData = $this->config->getArray('datenschutz');
        $title = (string) ($legalData['title'] ?? 'Datenschutzerklärung');
        $vereinsName = $this->config->getString('vereins_name', 'KGA');

        $verantwortlich = \is_array($legalData['verantwortlich'] ?? null) ? $legalData['verantwortlich'] : [];
        $aufsicht = \is_array($legalData['aufsichtsbehoerde'] ?? null) ? $legalData['aufsichtsbehoerde'] : [];
        $rawSections = \is_array($legalData['sections'] ?? null) ? $legalData['sections'] : [];

        $sections = [];
        foreach ($rawSections as $sec) {
            if (!\is_array($sec)) {
                continue;
            }
            $sections[] = new DatenschutzSectionViewDto(
                title: (string) ($sec['title'] ?? ''),
                text: (string) ($sec['text'] ?? ''),
            );
        }

        $viewDto = new DatenschutzViewDto(
            title: $title,
            lastUpdated: (string) ($legalData['letzte_aktualisierung'] ?? ''),
            responsibleName: (string) ($verantwortlich['name'] ?? ''),
            responsibleAddress: (string) ($verantwortlich['adresse'] ?? ''),
            responsibleEmail: (string) ($verantwortlich['email'] ?? ''),
            authorityName: (string) ($aufsicht['name'] ?? ''),
            authorityAddress: (string) ($aufsicht['adresse'] ?? ''),
            sections: $sections,
        );

        $html = $this->renderer->render('frontend/datenschutz', [
            'viewDto' => $viewDto,
            'pageTitle' => $title . ' - ' . $vereinsName,
        ]);

        return new HtmlResponse($html);
    }
}
