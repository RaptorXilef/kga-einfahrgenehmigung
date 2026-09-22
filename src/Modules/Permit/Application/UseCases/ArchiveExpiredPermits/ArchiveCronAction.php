<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;

#[Route('GET', '/api/cron/archive')]
#[Route('POST', '/api/cron/archive')]
final readonly class ArchiveCronAction implements ViewActionInterface
{
    public function __construct(
        private ArchiveExpiredPermitsHandler $archiveHandler,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private ConfigInterface $config,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        if (($request->get['token'] ?? '') !== (string) $this->config->get('cron_secret', '')) {
            return JsonResponse::error('Unautorisiert.', 403);
        }

        $graceDays = (int) $this->config->get('archive_grace_days', 0);
        $this->archiveHandler->handle(new ArchiveExpiredPermitsCommand($graceDays));
        $anonymizedCount = $this->archiveRepository->anonymizeOldRecords(10);

        return JsonResponse::success([
            'message' => 'Bereinigung erfolgreich durchgelaufen.',
            'anonymized' => $anonymizedCount,
        ]);
    }
}
