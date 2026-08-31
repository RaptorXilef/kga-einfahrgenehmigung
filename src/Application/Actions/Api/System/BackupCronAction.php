<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\System;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;

#[Route('GET', '/api/cron/backup')]
#[Route('POST', '/api/cron/backup')]
final readonly class BackupCronAction implements ViewActionInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        if (($request->get['token'] ?? '') !== (string) $this->config->get('cron_secret', '')) {
            return JsonResponse::error('Unautorisiert.', 403);
        }

        $this->backupService->runCronBackup();

        return JsonResponse::success(['message' => 'Automatisches Backup erstellt und alte Dateien rotiert.']);
    }
}
