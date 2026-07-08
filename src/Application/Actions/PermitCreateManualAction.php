<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\PermitCreateManualRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
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
        } catch (ValidationException|\InvalidArgumentException $e) {
            // UX-Rettung: Eingegebene Formulardaten vor dem Redirect in der Session sichern
            $postData = $request->post;
            unset($postData['csrf_token']);
            $this->sessionManager->setFormData($postData);

            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php?focus=tab-tools');
        }

        try {
            $this->permitService->createPermit($dto->formData, $dto->sendEmail);

            // LOG SCHREIBEN
            $this->auditLogger->log('PERMIT_CREATE', "Manuelle Genehmigung erstellt für: {$dto->formData->name} (Parzelle {$dto->formData->parzelle->getFormatted()})");

            $this->sessionManager->addFlash('success', 'Manuelle Genehmigung wurde erfolgreich erstellt.');

            // Wenn erfolgreich, schicken wir den User auf den Aktive-Reiter
            return new RedirectResponse('admin.php?focus=tab-active');

        } catch (\InvalidArgumentException $e) {
            $postData = $request->post;
            unset($postData['csrf_token']);
            $this->sessionManager->setFormData($postData);
            $this->sessionManager->addFlash('error', 'Fehler: ' . $e->getMessage());

            return new RedirectResponse('admin.php?focus=tab-tools');
        } catch (\Throwable $e) {
            $postData = $request->post;
            unset($postData['csrf_token']);
            $this->sessionManager->setFormData($postData);
            $this->sessionManager->addFlash('error', 'Kritischer Fehler: ' . $e->getMessage());

            return new RedirectResponse('admin.php?focus=tab-tools');
        }
    }
}
