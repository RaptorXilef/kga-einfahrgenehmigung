<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitToggleSuspensionRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
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
            return new RedirectResponse('admin.php?msg=' . \urlencode($e->getMessage()));
        }
        if ($this->permitService->toggleSuspension($dto->code, $dto->isSuspended, $dto->reason)) {
            $msg = 'Genehmigung wurde ' . ($dto->isSuspended ? 'gesperrt.' : 'freigegeben.');

            return new RedirectResponse('admin.php?msg=' . \urlencode($msg));
        }

        return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler: Genehmigung nicht gefunden.'));
    }
}
