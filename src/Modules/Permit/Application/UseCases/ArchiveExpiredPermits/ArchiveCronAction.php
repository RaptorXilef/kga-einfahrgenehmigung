<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use Override;

#[Route('GET', '/api/cron/archive')]
#[Route('POST', '/api/cron/archive')]
final readonly class ArchiveCronAction implements ViewActionInterface
{
    public function __construct(
        private ArchiveExpiredPermitsHandler $archiveHandler,
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        if (($request->get['token'] ?? '') !== $this->config->getString('cron_secret')) {
            return JsonResponse::error('Unautorisiert.', 403);
        }

        $graceDays = $this->config->getInt('archive_grace_days', 0);
        $anonymizedCount = $this->archiveHandler->handle(new ArchiveExpiredPermitsCommand($graceDays, 10));

        return JsonResponse::success([
            'message' => 'Bereinigung erfolgreich durchgelaufen.',
            'anonymized' => $anonymizedCount,
        ]);
    }
}
