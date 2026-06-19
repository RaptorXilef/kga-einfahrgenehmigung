<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\EmptyResponse;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Config\ConfigInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CronAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function process(array $requestData, callable $next): mixed
    {
        $provided = $requestData['get']['token'] ?? '';
        $req      = (string) $this->config->get('cron_secret', '');
        if (\php_sapi_name() !== 'cli' && $provided !== $req) {
            return new EmptyResponse(403);
        }

        return $next($requestData);
    }
}
