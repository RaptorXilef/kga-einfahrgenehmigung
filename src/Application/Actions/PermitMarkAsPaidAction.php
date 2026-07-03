<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\PermitService;

/**
 * Action zum manuellen Markieren einer Genehmigung als 'bezahlt'.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('mark_as_paid')]
final readonly class PermitMarkAsPaidAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private PermitService $permitService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.finance.mark_paid';
    }

    /**
     * Markiert eine Genehmigung manuell als bezahlt im Storage.
     *
     * Nutzt PermitService::manualActivate().
     *
     * @return string Erfolgsmeldung oder leerer String bei Fehler.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'code');
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        if ($this->permitService->manualActivate($dto->identifier)) {
            $this->sessionManager->addFlash('success', "Zahlung für {$dto->identifier} bestätigt.");
        } else {
            $this->sessionManager->addFlash('error', 'Fehler: Genehmigung nicht gefunden oder bereits bezahlt.');
        }

        return new RedirectResponse('admin.php');
    }
}
