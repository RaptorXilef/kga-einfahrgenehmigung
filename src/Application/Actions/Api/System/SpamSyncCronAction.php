<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\System;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\Security\EmailValidationService;

#[Route('GET', '/api/cron/spam_sync')]
#[Route('POST', '/api/cron/spam_sync')]
final readonly class SpamSyncCronAction implements ViewActionInterface
{
    public function __construct(
        private EmailValidationService $emailValidation,
        private ConfigInterface $config,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        if (($request->get['token'] ?? '') !== (string) $this->config->get('cron_secret', '')) {
            return JsonResponse::error('Unautorisiert. Ungültiges Cron-Token.', 403);
        }

        $this->emailValidation->syncDisposableDomains();

        return JsonResponse::success(['message' => 'Spam-Domains erfolgreich synchronisiert.']);
    }
}
