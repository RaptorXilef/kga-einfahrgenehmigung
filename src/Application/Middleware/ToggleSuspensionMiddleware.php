<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;

/**
 * Guard für das Sperren/Entsperren von Genehmigungen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ToggleSuspensionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private StorageInterface $storage,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        $code = \trim((string) ($request->post['code'] ?? ''));
        if ($code === '') {
            return $next($request);
        }

        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return $next($request);
        }

        $isUnpaid = \strtolower(\trim($permit->getStatus())) !== 'bezahlt';
        $hasRight = false;

        if ($isUnpaid && $this->auth->hasPermission('dashboard.finance.suspend')) {
            $hasRight = true;
        } elseif (! $isUnpaid && $this->auth->hasPermission('dashboard.active.suspend')) {
            $hasRight = true;
        }

        if (! $hasRight) {
            return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler: Keine Berechtigung zum Sperren.'));
        }

        return $next($request);
    }
}
