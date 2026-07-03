<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\DashboardFilterRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;

/**
 * Action zum Speichern der Dashboard-Filter in der aktuellen Session.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('filter_dashboard')]
final readonly class DashboardFilterAction implements ActionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
    ) {
    }

    /**
     * Hilfsmethode zum Speichern der Dashboard-Filter in der aktuellen Session.
     *
     * @return string Statusmeldung über den Erfolg der Anwendung.
     */
    public function execute(ServerRequest $request): mixed
    {
        $dto = DashboardFilterRequest::fromArray($request->post);
        $this->sessionManager->setAdminFilters([
            'end'   => $dto->end,
            'limit' => $dto->limit,
            'q'     => $dto->q,
            'start' => $dto->start,
            'type'  => $dto->type,
        ]);

        $this->sessionManager->addFlash('success', 'Filter angewendet.');

        return new RedirectResponse('admin.php');
    }
}
