<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Application\Session\SessionManager;
use App\Core\Service\AuditLoggerService;

#[Route('POST', '/api/ping')]
final readonly class SystemExtendSessionAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $userId = $this->sessionManager->getUserId();

        if ($userId !== '') {
            $this->auditLogger->log('SESSION_EXTENDED', 'Warnung ignoriert: Sitzung wurde durch den Button manuell verlängert.');
        } else {
            $email = (string) $this->sessionManager->getHistoryEmail();
            if ($email !== '') {
                $this->auditLogger->log('USER_SESSION_EXTENDED', "Pächter (Email: {$email}) hat die Sitzung manuell verlängert.");
            }
        }

        return JsonResponse::success(['status' => 'extended']);
    }
}
