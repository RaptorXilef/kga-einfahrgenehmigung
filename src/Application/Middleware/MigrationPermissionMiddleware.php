<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MigrationPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        $target = $request->post['target'] ?? '';
        $dir    = $request->post['direction'] ?? '';
        if (! $this->auth->hasPermission("dashboard.migration.{$target}.{$dir}")) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung für diese Migrations-Aktion.');

            return new RedirectResponse('admin.php');
        }

        return $next($request);
    }
}
