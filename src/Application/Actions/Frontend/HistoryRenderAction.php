<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\ViewRenderRequest;
use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Core\Service\PermitService;

#[Route('GET', '/history')]
final readonly class HistoryRenderAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private PermitArchiveRepositoryInterface $archiveRepository, // NEU INJIZIERT
        private PermitService $permitService,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = ViewRenderRequest::fromArray($request->get);
        $emailInSession = (string) $this->sessionManager->getHistoryEmail();

        if ($emailInSession === '') {
            $this->renderer->render('frontend/history_login', [
                'isSuccess' => $dto->isSuccess,
                'step' => $dto->step,
            ]);

            return null;
        }

        $permits = $this->permitService->getHistoryByEmail($emailInSession);
        $loadedYear = $dto->loadArchive;

        // --- FIX: Wir laden das Archiv nun sauber aus MySQL statt JSON! ---
        if ($loadedYear > 0) {
            $archivedPermits = $this->archiveRepository->getArchivedPermits($loadedYear);
            foreach ($archivedPermits as $p) {
                if (\strtolower($p->getOwnerEmail()) !== \strtolower($emailInSession)) {
                    continue;
                }

                $permits[] = $p;
            }
        }

        \usort($permits, fn ($a, $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        $overdueLevels = [];
        foreach ($permits as $permit) {
            $overdueLevels[$permit->code->value] = $this->permitService->getOverdueLevel($permit);
        }

        $this->renderer->render('frontend/history_list', [
            'currentArchiveYear' => $loadedYear,
            'email' => $emailInSession,
            'isSuccess' => $dto->isSuccess,
            'overdueLevels' => $overdueLevels,
            'permits' => $permits,
        ]);

        return null;
    }
}
