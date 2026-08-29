<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\System;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Maintenance\MigrationServiceInterface;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Contracts\System\RouteCacheInterface;
use Throwable;

#[Route('GET', '/api/system_update')]
#[Route('POST', '/api/system_update')]
final readonly class SystemUpdateAction implements ActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private RouteCacheInterface $routeCache,
        private MigrationServiceInterface $migrationService,
        private UpdateMigrationServiceInterface $updateMigrationService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $tokenRaw = $request->get['token'] ?? '';
        $providedToken = \is_string($tokenRaw) ? $tokenRaw : '';

        $cronRaw = $this->config->get('cron_secret', '');
        $expectedToken = \is_string($cronRaw) ? $cronRaw : '';

        if ($expectedToken === '' || $providedToken !== $expectedToken) {
            return JsonResponse::error('Unautorisiert. Ungültiges Deployment-Token.', 403);
        }

        try {
            // 1. Cache leeren (Routen & System)
            $this->routeCache->clearAll();
            $this->migrationService->clearCache();

            // 2. Datenbank-Migrationen ausführen (z.B. 018_rename...)
            $migrationsApplied = $this->updateMigrationService->runAllPending();

            return JsonResponse::success([
                'message' => 'System-Update erfolgreich! Cache geleert & DB-Schema geprüft.',
                'migrations_applied' => $migrationsApplied,
            ]);
        } catch (Throwable $e) {
            return JsonResponse::error('Fehler beim System-Update: ' . $e->getMessage(), 500);
        }
    }
}
