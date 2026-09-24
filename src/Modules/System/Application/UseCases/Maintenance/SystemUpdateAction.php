<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\Maintenance;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Contracts\System\RouteCacheInterface;
use Override;
use Throwable;

#[Route('GET', '/api/system_update')]
#[Route('POST', '/api/system_update')]
final readonly class SystemUpdateAction implements ActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private RouteCacheInterface $routeCache,
        private UpdateMigrationServiceInterface $updateMigrationService,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $tokenRaw = $request->get['token'] ?? '';
        $providedToken = \is_string($tokenRaw) ? $tokenRaw : '';

        $expectedToken = $this->config->getString('cron_secret');

        if ($expectedToken === '' || $providedToken !== $expectedToken) {
            return JsonResponse::error('Unautorisiert. Ungültiges Deployment-Token.', 403);
        }

        try {
            $this->routeCache->clearAll();
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
