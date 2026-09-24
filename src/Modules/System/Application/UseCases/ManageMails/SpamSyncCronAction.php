<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Modules\System\Application\Services\EmailValidationService;
use Override;

#[Route('GET', '/api/cron/spam_sync')]
#[Route('POST', '/api/cron/spam_sync')]
final readonly class SpamSyncCronAction implements ViewActionInterface
{
    public function __construct(
        private EmailValidationService $emailValidation,
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        if (($request->get['token'] ?? '') !== $this->config->getString('cron_secret')) {
            return JsonResponse::error('Unautorisiert. Ungültiges Cron-Token.', 403);
        }

        $this->emailValidation->syncDisposableDomains();

        return JsonResponse::success(['message' => 'Spam-Domains erfolgreich synchronisiert.']);
    }
}
