<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use Override;

/**
 * Middleware zur Validierung der zulässigen HTTP-Methoden.
 */
final readonly class HttpMethodMiddleware implements MiddlewareInterface
{
    public function __construct(private array $allowedMethods = ['POST'])
    {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $method = $request->getMethod();
        if (!\in_array($method, $this->allowedMethods, true)) {
            return JsonResponse::error('Methode nicht erlaubt.', 405);
        }

        return $next($request);
    }
}
