<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Storage\LockManagerInterface;
use Override;

#[Route('GET', '/api/process_mail_queue')]
#[Route('POST', '/api/process_mail_queue')]
final readonly class ProcessMailQueueAction implements ActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private MailServiceInterface $mailService,
        private LockManagerInterface $lockManager,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $cronSecret = $this->config->getString('cron_secret');
        $token = $request->get['token'] ?? '';

        $isCron = $request->getMethod() === 'GET' && $token !== '' && $token === $cronSecret;
        $isFrontend = $request->getMethod() === 'POST';

        if (!$isCron && !$isFrontend) {
            return JsonResponse::error('Unautorisierter Zugriff.', 401);
        }

        // Wir nutzen nun den sauberen LockManager statt nativer I/O-Funktionen!
        return $this->lockManager->executeWithLock('kga_mail_queue', function () use ($isCron): JsonResponse {
            $limit = $isCron ? 20 : 3;
            $processed = $this->mailService->processQueue($limit);

            return JsonResponse::success([
                'status' => 'ok',
                'processed' => $processed,
                'trigger' => $isCron ? 'cron' : 'frontend',
            ]);
        });
    }
}
