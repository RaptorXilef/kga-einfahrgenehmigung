<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;

/**
 * Guard für die Druck-Berechtigung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PrintAuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        $code = (string) ($request->get['code'] ?? '');
        if ($code === '') {
            return $next($request);
        }

        $permit = $this->storage->findByHash($code);
        if (!$permit instanceof Permit) {
            return $next($request);
        }

        if (!$this->auth->hasPermission('permits.print')) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung zum Drucken dieser Genehmigung.');

            return new RedirectResponse('admin.php');
        }

        return $next($request);
    }
}
