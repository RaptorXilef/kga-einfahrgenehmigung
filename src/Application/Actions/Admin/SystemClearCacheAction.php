<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\RouteCacheInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;

#[Route('POST', '/clear_cache')]
final readonly class SystemClearCacheAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private RouteCacheInterface $routeCache,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.maintenance.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        // 1. Routen-Cache (cache/routes_v2.php) komplett löschen!
        $this->routeCache->clearAll();

        // 2. Berechtigungen der aktuellen Session neu kompilieren
        $this->auth->refreshSessionPermissions($this->auth->getRole());

        $this->auditLogger->log('SYSTEM_CACHE_CLEAR', 'Der System-Cache und Routen-Cache wurden manuell geleert.');
        $this->sessionManager->addFlash('success', 'Erfolg: Cache und Routen wurden erfolgreich geleert.');

        return new RedirectResponse('admin');
    }
}
