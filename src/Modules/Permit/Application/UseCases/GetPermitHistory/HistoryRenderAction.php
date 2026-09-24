<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitHistory;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\SubmitPermitRequest\ViewRenderRequest;

#[Route('GET', '/history')]
final readonly class HistoryRenderAction implements ViewActionInterface
{
    public function __construct(
        private GetPermitHistoryHandler $historyHandler,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = ViewRenderRequest::fromArray($request->get);
        $emailInSession = (string) $this->sessionManager->getHistoryEmail();

        if ($emailInSession === '') {
            $html = $this->renderer->render('frontend/history_login', [
                'isSuccess' => $dto->isSuccess,
                'step' => $dto->step,
            ]);

            return new HtmlResponse($html);
        }

        $permitsDto = $this->historyHandler->handle(new GetPermitHistoryQuery($emailInSession, $dto->loadArchive));
        $lastYear = (int) $this->clock->now()->format('Y') - 1;

        $html = $this->renderer->render('frontend/history_list', [
            'currentArchiveYear' => $dto->loadArchive,
            'lastYear' => $lastYear,
            'email' => $emailInSession,
            'isSuccess' => $dto->isSuccess,
            'permitsDto' => $permitsDto,
        ]);

        return new HtmlResponse($html);
    }
}
