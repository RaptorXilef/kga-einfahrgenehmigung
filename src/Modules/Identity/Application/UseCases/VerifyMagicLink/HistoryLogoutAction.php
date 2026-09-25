<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\VerifyMagicLink;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use Override;

#[Route('GET', '/history_logout')]
#[Route('POST', '/history_logout')]
final readonly class HistoryLogoutAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $email = (string) $this->sessionManager->getHistoryEmail();
        if ($email !== '') {
            $this->auditLogger->log('USER_HISTORY_LOGOUT', "Pächter (Email: {$email}) hat sich abgemeldet.");
        }

        $this->sessionManager->clearHistoryEmail();

        return new RedirectResponse('history');
    }
}
