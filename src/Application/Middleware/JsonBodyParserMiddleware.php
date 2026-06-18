<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\MiddlewareInterface;

/**
 * Liest sichere JSON-Bodys asynchroner Anfragen aus und mappt sie in den Request.
 *
 * Path: src/Application/Middleware/JsonBodyParserMiddleware.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(array $requestData, callable $next): mixed
    {
        $method      = $_SERVER['REQUEST_METHOD'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (\in_array($method, ['POST', 'PUT', 'PATCH'], true) && \str_contains($contentType, 'application/json')) {
            $raw = \file_get_contents('php://input');
            if ($raw !== '' && $raw !== false) {
                try {
                    $requestData['input'] = \json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    JsonResponse::error('Bad Request: Ungültiges JSON-Format gesendet.', 400);

                    return null; // Pipeline abbrechen
                }
            }
        }

        return $next($requestData);
    }
}
