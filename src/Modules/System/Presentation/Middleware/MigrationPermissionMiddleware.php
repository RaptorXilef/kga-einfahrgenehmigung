<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Application\Services\AuthService;
use Override;

final readonly class MigrationPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        if (!$this->auth->hasPermission('system.maintenance.execute')) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung für diese Migrations-Aktion.');

            return new RedirectResponse('admin');
        }

        return $next($request);
    }
}
