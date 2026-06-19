<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\RedirectResponse;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;

/**
 * Guard für die Druck-Berechtigung basierend auf dem Status der Genehmigung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PrintAuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private StorageInterface $storage,
    ) {
    }

    public function process(array $requestData, callable $next): mixed
    {
        $code = (string) ($requestData['get']['code'] ?? '');
        if ($code === '') {
            return $next($requestData);
        }

        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return $next($requestData);
        }

        $now       = new \DateTimeImmutable('today');
        $isExpired = $permit->getValidUntil() < $now;
        $isFuture  = $permit->getValidFrom() > $now;

        $hasRight = false;
        if ($this->auth->hasPermission('check.admin.print')) {
            $hasRight = true;
        } elseif ($isExpired && $this->auth->hasPermission('dashboard.expired.print')) {
            $hasRight = true;
        } elseif ($isFuture && $this->auth->hasPermission('dashboard.future.print')) {
            $hasRight = true;
        } elseif (! $isExpired && ! $isFuture && $this->auth->hasPermission('dashboard.active.print')) {
            $hasRight = true;
        }

        if (! $hasRight) {
            return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler: Keine Berechtigung zum Drucken dieser Genehmigung.'));
        }

        return $next($requestData);
    }
}
