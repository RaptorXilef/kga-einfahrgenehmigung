<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\DTO\PermitToggleSuspensionRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Permit\Application\UseCases\TogglePermitSuspension\TogglePermitSuspensionCommand;
use App\Modules\Permit\Application\UseCases\TogglePermitSuspension\TogglePermitSuspensionHandler;
use DomainException;
use Modules\System\Application\Services\AuditLoggerService;

/**
 * Action zum Sperren oder Entsperren einer aktiven Genehmigung (VSA CQRS).
 */
#[Route('GET', '/suspend_permit')]
#[Route('POST', '/suspend_permit')]
#[Route('GET', '/unsuspend_permit')]
#[Route('POST', '/unsuspend_permit')]
final readonly class PermitToggleSuspensionAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private TogglePermitSuspensionHandler $toggleHandler,
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

            return new RedirectResponse('admin');
        }

        try {
            $command = new TogglePermitSuspensionCommand($dto->code, $dto->isSuspended, $dto->reason);
            $this->toggleHandler->handle($command);

            $actionStr = $dto->isSuspended ? 'gesperrt' : 'freigegeben';
            $msg = 'Genehmigung wurde ' . $actionStr . '.';

            $this->auditLogger->log('PERMIT_SUSPENSION', "Genehmigung '{$dto->code}' wurde {$actionStr}. Grund: {$dto->reason}");
            $this->sessionManager->addFlash('success', $msg);

        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', 'Fehler: ' . $e->getMessage());
        }

        return new RedirectResponse('admin');
    }
}
