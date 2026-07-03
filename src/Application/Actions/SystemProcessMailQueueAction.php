<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;

/**
 * Action zum manuellen Anstoßen der Mail-Warteschlange.
 * Oder zum anstoßen per cronjob
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('process_mail_queue')]
final readonly class SystemProcessMailQueueAction implements ViewActionInterface
{
    public function __construct(
        private MailServiceInterface $mailService,
        private ConfigInterface $config,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        // 1. Sicherheit: Entweder valider CSRF-Token (vom Admin-Dashboard) ODER valider Cron-Token (von extern)
        $isCron            = false;
        $providedToken     = $request->get['token'] ?? '';
        $expectedCronToken = (string) $this->config->get('cron_secret', '');

        if ($providedToken === $expectedCronToken && $expectedCronToken !== '') {
            $isCron = true;
        }

        // Wenn weder Cron-Token gültig, noch ein valider Admin-Post-Request (CSRF wird durch Middleware geprüft)
        if (! $isCron && $request->getMethod() !== 'POST') {
            return JsonResponse::error('Unautorisiert.', 403);
        }

        if (\method_exists($this->mailService, 'processQueue')) {
            // Limits dynamisch aus der Konfiguration laden
            $cronLimit  = (int) $this->config->get('mail_queue_limit_cron', 50);
            $adminLimit = (int) $this->config->get('mail_queue_limit_admin', 10);

            $limit = $isCron ? $cronLimit : $adminLimit;

            $sent = $this->mailService->processQueue($limit);

            return JsonResponse::success([
                'status'     => 'processed',
                'sent_count' => $sent,
                'mode'       => $isCron ? 'cron' : 'admin_trigger',
            ]);
        }

        return JsonResponse::success(['status' => 'skipped_no_queue']);
    }
}
