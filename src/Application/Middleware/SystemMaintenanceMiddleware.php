<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Contracts\System\StorageBootstrapperInterface;
use Throwable;

final readonly class SystemMaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private StorageBootstrapperInterface $bootstrapper,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        try {
            $this->bootstrapper->bootstrap();
        } catch (Throwable $e) {
            \error_log('Bootstrapping Warning: ' . $e->getMessage());
        }

        return $next($request);
    }
}
