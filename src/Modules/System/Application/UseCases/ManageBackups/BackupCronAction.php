<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use Override;

#[Route('GET', '/api/cron/backup')]
#[Route('POST', '/api/cron/backup')]
final readonly class BackupCronAction implements ViewActionInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        if (($request->get['token'] ?? '') !== $this->config->getString('cron_secret')) {
            return JsonResponse::error('Unautorisiert.', 403);
        }

        $this->backupService->runCronBackup();

        return JsonResponse::success([
            'message' => 'Backup erfolgreich erstellt und rotiert.',
        ]);
    }
}
