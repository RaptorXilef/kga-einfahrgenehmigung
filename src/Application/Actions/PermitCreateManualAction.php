<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\PermitCreateManualRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\PermitService;

/**
 * Action zur manuellen Ausstellung einer Genehmigung (ohne Zahlungsfluss).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('create_manual')]
final readonly class PermitCreateManualAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private PermitService $permitService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.generator-tools.direct_issue.execute';
    }

    /**
     * Erstellt eine Genehmigung ohne vorangegangenen automatisierten Bezahlprozess.
     *
     * Erzwingt 'status' = 'bezahlt' und nutzt PermitService::createPermit().
     *
     * @return string Bestätigung mit dem generierten Genehmigungscode.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = PermitCreateManualRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        try {
            $this->permitService->createPermit($dto->formData, $dto->sendEmail);

            // LOG SCHREIBEN
            $this->auditLogger->log('PERMIT_CREATE', "Manuelle Genehmigung erstellt für: {$dto->formData->name} (Parzelle {$dto->formData->parzelle})");

            $this->sessionManager->addFlash('success', 'Manuelle Genehmigung wurde erfolgreich erstellt.');

            return new RedirectResponse('admin.php');
        } catch (\Exception $e) {
            $this->sessionManager->addFlash('error', 'Fehler: ' . $e->getMessage());

            return new RedirectResponse('admin.php');
        }
    }
}
