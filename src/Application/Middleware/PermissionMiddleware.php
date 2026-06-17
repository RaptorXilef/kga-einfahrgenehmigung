<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Contracts\Application\MiddlewareInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Middleware/PermissionMiddleware.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private string $requiredPermission,
        private string $fallbackUrl,
    ) {
    }

    // TODO DOCBLOCK
    public function process(array $requestData, callable $next): mixed
    {
        if (! $this->auth->hasPermission($this->requiredPermission)) {
            \header("Location: {$this->fallbackUrl}");
            exit;
        }

        return $next($requestData);
    }
}
