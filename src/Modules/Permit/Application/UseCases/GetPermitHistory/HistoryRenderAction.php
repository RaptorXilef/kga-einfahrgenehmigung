<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitHistory;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Permit\Application\UseCases\SubmitPermitRequest\ViewRenderRequest;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\SharedKernel\Application\Security\Sanitizer;

#[Route('GET', '/history')]
final readonly class HistoryRenderAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private GetPermitHistoryHandler $historyHandler,
        private PermitFinancialCalculator $financialCalculator,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
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

        $permits = $this->historyHandler->handle(new GetPermitHistoryQuery($emailInSession));
        $loadedYear = $dto->loadArchive;

        if ($loadedYear > 0) {
            $archivedPermits = $this->archiveRepository->getArchivedPermits($loadedYear);
            $normalizedSessionEmail = Sanitizer::normalizeEmail($emailInSession);

            foreach ($archivedPermits as $p) {
                if (Sanitizer::normalizeEmail($p->getOwnerEmail()) !== $normalizedSessionEmail) {
                    continue;
                }
                $permits[] = $p;
            }
        }

        \usort($permits, fn ($a, $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        $overdueLevels = [];
        foreach ($permits as $permit) {
            $overdueLevels[$permit->code->value] = $this->financialCalculator->getOverdueLevel($permit);
        }

        $html = $this->renderer->render('frontend/history_list', [
            'currentArchiveYear' => $loadedYear,
            'email' => $emailInSession,
            'isSuccess' => $dto->isSuccess,
            'overdueLevels' => $overdueLevels,
            'permits' => $permits,
        ]);

        return new HtmlResponse($html);
    }
}
