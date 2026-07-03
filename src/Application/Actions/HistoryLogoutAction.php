<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\AuditLoggerService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('history_logout')]
final readonly class HistoryLogoutAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
    ) {
    }

    /**
     * Verarbeitet den Logout-Prozess für die History-Sitzung.
     */
    public function execute(ServerRequest $request): mixed
    {
        $email = (string) $this->sessionManager->getHistoryEmail();
        if ($email !== '') {
            $this->auditLogger->log('USER_HISTORY_LOGOUT', "Pächter (Email: {$email}) hat sich abgemeldet.");
        }

        $this->sessionManager->clearHistoryEmail();

        return new RedirectResponse('history.php');
    }
}
