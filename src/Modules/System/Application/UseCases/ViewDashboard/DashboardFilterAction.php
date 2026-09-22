<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;

#[Route('GET', '/filter_dashboard')]
#[Route('POST', '/filter_dashboard')]
final readonly class DashboardFilterAction implements ActionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = DashboardFilterRequest::fromArray($request->post);
        $this->sessionManager->setAdminFilters([
            'end' => $dto->end,
            'limit' => $dto->limit,
            'q' => $dto->q,
            'start' => $dto->start,
            'type' => $dto->type,
        ]);

        $this->sessionManager->addFlash('success', 'Filter angewendet.');

        return new RedirectResponse('admin');
    }
}
