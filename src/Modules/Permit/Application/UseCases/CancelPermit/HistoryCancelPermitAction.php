<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CancelPermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use DomainException;
use Override;

#[Route('GET', '/history_cancel_permit')]
#[Route('POST', '/history_cancel_permit')]
final readonly class HistoryCancelPermitAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private CancelPermitHandler $cancelHandler,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
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
