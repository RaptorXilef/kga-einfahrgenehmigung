<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Maintenance\MigrationServiceInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;

/**
 * Action zum Leeren des Anwendungs-Caches.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('clear_cache')]
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
        return 'dashboard.migration.delete-cache.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        $msg = $this->migrationService->clearCache();

        // Session-Rechte neu kompilieren (für den aktuellen Admin)
        $this->auth->refreshSessionPermissions($this->auth->getGroup());

        $this->auditLogger->log('SYSTEM_CACHE_CLEAR', 'Der System-Cache wurde manuell geleert.');
        $this->sessionManager->addFlash('success', $msg);

        return new RedirectResponse('admin.php');
    }
}
