<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\System\AuditLoggerInterface;
use Override;

#[Route('GET', '/admin_logout')]
#[Route('POST', '/admin_logout')]
final readonly class AdminLogoutAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private AuthorizationInterface $auth,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $this->auditLogger->log('LOGOUT', 'Abmeldung aus dem System.');
        $this->auth->logout();

        return new RedirectResponse('admin');
    }
}
