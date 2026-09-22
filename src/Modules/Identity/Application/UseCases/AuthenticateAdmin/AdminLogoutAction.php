<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\System\Application\Services\AuditLoggerService;

#[Route('GET', '/admin_logout')]
#[Route('POST', '/admin_logout')]
final readonly class AdminLogoutAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $this->auditLogger->log('LOGOUT', 'Abmeldung aus dem System.');
        $this->auth->logout();

        return new RedirectResponse('admin');
    }
}
