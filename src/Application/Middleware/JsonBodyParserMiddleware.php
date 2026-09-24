<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use JsonException;
use Override;

/**
 * Liest sichere JSON-Bodys asynchroner Anfragen aus und mappt sie in den Request.
 */
final readonly class JsonBodyParserMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $method = $request->getMethod();
        $contentType = $request->getContentType();

        if (\in_array($method, ['POST', 'PUT', 'PATCH'], true) && \str_contains($contentType, 'application/json')) {
            $raw = \file_get_contents('php://input');
            if ($raw !== '' && $raw !== false) {
                try {
                    $request = $request->withInput(\json_decode($raw, true, 512, \JSON_THROW_ON_ERROR));
                } catch (JsonException) {
                    return JsonResponse::error('Bad Request: Ungültiges JSON-Format gesendet.', 400);
                }
            }
        }

        return $next($request);
    }
}
