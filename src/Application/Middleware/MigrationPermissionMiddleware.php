<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\RedirectResponse;
use App\Contracts\Application\MiddlewareInterface;
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
    ) {
    }

    public function process(array $requestData, callable $next): mixed
    {
        $target = $requestData['post']['target'] ?? '';
        $dir    = $requestData['post']['direction'] ?? '';
        if (! $this->auth->hasPermission("dashboard.migration.{$target}.{$dir}")) {
            return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler: Keine Berechtigung für diese Migrations-Aktion.'));
        }

        return $next($requestData);
    }
}
