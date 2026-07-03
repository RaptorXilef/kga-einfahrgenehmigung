<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitToggleSuspensionRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\PermitService;

/**
 * Action zum Sperren oder Entsperren einer aktiven Genehmigung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitToggleSuspensionAction implements ActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private SessionManager $sessionManager,
    ) {
    }

    /**
     * TODO DOCBLOCK
     * Setzt den Sperrstatus (Suspension) einer Genehmigung.
     * Kontext: Interaktion mit PermitService::toggleSuspension().
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = PermitToggleSuspensionRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        if ($this->permitService->toggleSuspension($dto->code, $dto->isSuspended, $dto->reason)) {
            $msg = 'Genehmigung wurde ' . ($dto->isSuspended ? 'gesperrt.' : 'freigegeben.');
            $this->sessionManager->addFlash('success', $msg);

            return new RedirectResponse('admin.php');
        }

        $this->sessionManager->addFlash('error', 'Fehler: Genehmigung nicht gefunden.');

        return new RedirectResponse('admin.php');
    }
}
