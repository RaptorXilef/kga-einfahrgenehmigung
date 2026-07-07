<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;

/**
 * Action für den sicheren Logout von Administratoren.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('admin_logout')]
final readonly class AdminLogoutAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $this->auditLogger->log('LOGOUT', 'Abmeldung aus dem System.');
        $this->auth->logout();

        return new RedirectResponse('admin.php');
    }
}
