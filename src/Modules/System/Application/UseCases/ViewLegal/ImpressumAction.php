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

        $rawBoard = \is_array($legalData['vorstand'] ?? null) ? $legalData['vorstand'] : [];
        $boardMembers = \array_values(\array_map(strval(...), $rawBoard));

        $kontakt = \is_array($legalData['kontakt'] ?? null) ? $legalData['kontakt'] : [];
        $register = \is_array($legalData['register'] ?? null) ? $legalData['register'] : [];
        $mstv = \is_array($legalData['verantwortlich_18_mstv'] ?? null) ? $legalData['verantwortlich_18_mstv'] : [];

        $ustId = \trim((string) ($legalData['ust_id'] ?? ''));

        $viewDto = new ImpressumViewDto(
            title: $title,
            clubName: (string) ($legalData['verein'] ?? ''),
            address: (string) ($legalData['adresse'] ?? ''),
            boardMembers: $boardMembers,
            phone: (string) ($kontakt['telefon'] ?? ''),
            email: (string) ($kontakt['email'] ?? ''),
            registerCourt: (string) ($register['gericht'] ?? ''),
            registerNumber: (string) ($register['nummer'] ?? ''),
            hasUstId: $ustId !== '',
            ustId: $ustId,
            responsiblePersonName: (string) ($mstv['name'] ?? ''),
            responsiblePersonAddress: (string) ($mstv['adresse'] ?? ''),
        );

        $html = $this->renderer->render('frontend/impressum', [
            'viewDto' => $viewDto,
            'pageTitle' => $title . ' - ' . $vereinsName,
        ]);

        return new HtmlResponse($html);
    }
}
