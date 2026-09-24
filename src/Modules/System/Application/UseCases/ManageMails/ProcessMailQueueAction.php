<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use Override;

#[Route('GET', '/api/process_mail_queue')]
#[Route('POST', '/api/process_mail_queue')]
final readonly class ProcessMailQueueAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private MailServiceInterface $mailService,
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

        $lockFile = \sys_get_temp_dir() . '/kga_mail_queue.lock';
        $lockHandle = \fopen($lockFile, 'w+');

        if (!$lockHandle || !\flock($lockHandle, \LOCK_EX | \LOCK_NB)) {
            return JsonResponse::success([
                'status' => 'skipped',
                'message' => 'Ein anderer Prozess arbeitet die Queue bereits ab.',
            ]);
        }

        try {
            $limit = $isCron ? 20 : 3;
            $processed = $this->mailService->processQueue($limit);

            return JsonResponse::success([
                'status' => 'ok',
                'processed' => $processed,
                'trigger' => $isCron ? 'cron' : 'frontend',
            ]);
        } finally {
            \flock($lockHandle, \LOCK_UN);
            \fclose($lockHandle);
        }
    }
}
