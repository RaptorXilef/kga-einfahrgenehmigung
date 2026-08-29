<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Maintenance\MigrationServiceInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;

#[Route('POST', '/clear_cache')]
final readonly class SystemClearCacheAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private MigrationServiceInterface $migrationService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.maintenance.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        $msg = $this->migrationService->clearCache();

        // FIX: getRole() statt getGroup()
        $this->auth->refreshSessionPermissions($this->auth->getRole());

        $this->auditLogger->log('SYSTEM_CACHE_CLEAR', 'Der System-Cache wurde manuell geleert.');
        $this->sessionManager->addFlash('success', $msg);

        return new RedirectResponse('admin.php');
    }
}
