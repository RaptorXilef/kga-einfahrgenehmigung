<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Contracts\System\StorageBootstrapperInterface;
use Override;
use Throwable;

final readonly class SystemMaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private StorageBootstrapperInterface $bootstrapper,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        try {
            $this->bootstrapper->bootstrap();
        } catch (Throwable $e) {
            \error_log('Bootstrapping Warning: ' . $e->getMessage());
        }

        return $next($request);
    }
}
