<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\System;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;

/**
 * Action zum Abarbeiten der E-Mail-Warteschlange.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/api/process_mail_queue')]
#[Route('POST', '/api/process_mail_queue')]
final readonly class ProcessMailQueueAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private MailServiceInterface $mailService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $cronSecret = (string) $this->config->get('cron_secret', '');
        $token = $request->get['token'] ?? '';

        $isCron = $request->getMethod() === 'GET' && $token !== '' && $token === $cronSecret;
        $isFrontend = $request->getMethod() === 'POST';

        if (!$isCron && !$isFrontend) {
            return JsonResponse::error('Unautorisierter Zugriff.', 401);
        }

        // --- TÜRSTEHER: Das File-Lock ---
        // Verhindert Race-Conditions und Serverüberlastung durch parallele Aufrufe via API/Cron.
        $lockFile = \sys_get_temp_dir() . '/kga_mail_queue.lock';
        $lockHandle = \fopen($lockFile, 'w+');

        // Versuche eine exklusive Sperre zu setzen (ohne zu warten = LOCK_NB)
        if (!$lockHandle || !\flock($lockHandle, \LOCK_EX | \LOCK_NB)) {
            return JsonResponse::success([
                'status' => 'skipped',
                'message' => 'Ein anderer Prozess arbeitet die Queue bereits ab.',
            ]);
        }

        try {
            // Cron arbeitet 20 Mails ab, der manuelle Admin-Button im Frontend nur 3
            $limit = $isCron ? 20 : 3;

            // FIX: Einfach die saubere Service-Methode aufrufen, statt eigene Closures zu bauen!
            $processed = $this->mailService->processQueue($limit);

            return JsonResponse::success([
                'status' => 'ok',
                'processed' => $processed,
                'trigger' => $isCron ? 'cron' : 'frontend',
            ]);
        } finally {
            // Die Sperre MUSS zwingend wieder aufgehoben werden, egal was passiert
            \flock($lockHandle, \LOCK_UN);
            \fclose($lockHandle);
        }
    }
}
