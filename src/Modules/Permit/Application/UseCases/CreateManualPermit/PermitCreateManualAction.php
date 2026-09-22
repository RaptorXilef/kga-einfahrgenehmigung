<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CreateManualPermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\System\Application\Services\AuditLoggerService;
use InvalidArgumentException;
use Throwable;

#[Route('GET', '/create_manual')]
#[Route('POST', '/create_manual')]
final readonly class PermitCreateManualAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private CreateManualPermitHandler $createHandler,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'permits.create';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = PermitCreateManualRequest::fromArray($request->post);
        } catch (ValidationException|InvalidArgumentException $e) {
            $postData = $request->post;
            unset($postData['csrf_token']);
            $this->sessionManager->setFormData($postData);
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin?focus=tab-tools');
        }

        try {
            $command = new CreateManualPermitCommand($dto->formData, $dto->sendEmail);
            $this->createHandler->handle($command);

            $this->auditLogger->log('PERMIT_CREATE', "Manuelle Genehmigung erstellt für: {$dto->formData->name} (Parzelle {$dto->formData->parzelle->getFormatted()})");
            $this->sessionManager->addFlash('success', 'Manuelle Genehmigung wurde erfolgreich erstellt.');

            return new RedirectResponse('admin?focus=tab-active');

        } catch (InvalidArgumentException $e) {
            $postData = $request->post;
            unset($postData['csrf_token']);
            $this->sessionManager->setFormData($postData);
            $this->sessionManager->addFlash('error', 'Fehler: ' . $e->getMessage());

            return new RedirectResponse('admin?focus=tab-tools');
        } catch (Throwable $e) {
            $postData = $request->post;
            unset($postData['csrf_token']);
            $this->sessionManager->setFormData($postData);
            $this->sessionManager->addFlash('error', 'Kritischer Fehler: ' . $e->getMessage());

            return new RedirectResponse('admin?focus=tab-tools');
        }
    }
}
