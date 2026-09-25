<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\Maintenance;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\System\AuditLoggerInterface;
use App\Contracts\System\RouteCacheInterface;
use Override;

#[Route('POST', '/clear_cache')]
final readonly class ClearCacheAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private AuthorizationInterface $auth,
        private RouteCacheInterface $routeCache,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.maintenance.execute';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $this->routeCache->clearAll();
        $this->auth->refreshSessionPermissions($this->auth->getRole());

        $this->auditLogger->log('SYSTEM_CACHE_CLEAR', 'Der System-Cache und Routen-Cache wurden manuell geleert.');
        $this->sessionManager->addFlash('success', 'Erfolg: Cache und Routen wurden erfolgreich geleert.');

        return new RedirectResponse('admin');
    }
}
