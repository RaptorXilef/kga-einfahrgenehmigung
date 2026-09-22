<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\HistoryCancelPermitRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Permit\Application\UseCases\CancelPermit\CancelPermitCommand;
use App\Modules\Permit\Application\UseCases\CancelPermit\CancelPermitHandler;
use App\Modules\System\Application\Services\AuditLoggerService;
use DomainException;

#[Route('GET', '/history_cancel_permit')]
#[Route('POST', '/history_cancel_permit')]
final readonly class HistoryCancelPermitAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private CancelPermitHandler $cancelHandler, // CQRS
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = HistoryCancelPermitRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history');
        }

        $email = (string) $this->sessionManager->getHistoryEmail();
        if ($email === '') {
            return new RedirectResponse('history');
        }

        try {
            $this->cancelHandler->handle(new CancelPermitCommand($dto->code, $email));

            $this->auditLogger->log('USER_PERMIT_CANCEL', "Pächter (Email: {$email}) hat die Genehmigung '{$dto->code}' selbstständig storniert.");
            $this->sessionManager->addFlash('success', 'Genehmigung wurde erfolgreich storniert.');

            return new RedirectResponse('history');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history');
        }
    }
}
