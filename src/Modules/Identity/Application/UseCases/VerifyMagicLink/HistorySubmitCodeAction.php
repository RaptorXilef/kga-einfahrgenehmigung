<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\VerifyMagicLink;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\System\Application\Services\AuditLoggerService;
use DomainException;
use Override;

#[Route('GET', '/history_submit_code')]
#[Route('POST', '/history_submit_code')]
final readonly class HistorySubmitCodeAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private VerifyMagicLinkHandler $verifyHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = HistorySubmitCodeRequest::fromRequest($request);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history?sent=1');
        }

        try {
            $command = new VerifyMagicLinkCommand($dto->loginCode, $dto->ip);
            $this->verifyHandler->handle($command);

            $verifiedEmail = $this->sessionManager->getHistoryEmail();
            $this->auditLogger->log('USER_HISTORY_LOGIN', "Pächter (Email: {$verifiedEmail}) hat sich im Genehmigungsverlauf eingeloggt.");

            return new RedirectResponse('history');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history?sent=1');
        }
    }
}
