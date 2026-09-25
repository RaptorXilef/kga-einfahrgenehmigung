<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use DomainException;
use Override;

#[Route('POST', '/resend_mail')]
#[RequiresAuth]
final readonly class ResendMailAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private ResendMailHandler $resendHandler,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.logs.view';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = ResendMailRequest::fromArray($request->post);
            $result = $this->resendHandler->handle(new ResendMailCommand($dto->timestamp));

            $this->auditLogger->log('SYSTEM_MAIL_RESEND', "E-Mail '{$result->subject}' an {$result->recipient} manuell erneut versendet.");
            $this->sessionManager->addFlash('success', "E-Mail an {$result->recipient} wurde erfolgreich erneut versendet.");
        } catch (ValidationException|DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-logs');
    }
}
