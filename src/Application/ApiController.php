<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\ApiActionFactory;
use App\Application\Middleware\ApiCsrfMiddleware;
use App\Application\Middleware\ApiPermissionMiddleware;
use App\Application\Middleware\ApiRateLimitMiddleware;
use App\Application\Middleware\HttpMethodMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Response\JsonResponse;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\AuthService;

/**
 * Zentraler Front-Controller für alle asynchronen (JSON & Form) API-Routen.
 *
 * Path: src/Application/ApiController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ApiController
{
    public function __construct(
        private ApiActionFactory $factory,
        private AuthService $auth,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    public function handle(string $actionKey, ?string $permission = null, bool $rateLimit = false): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit; // Pre-flight Requests direkt durchwinken
        }

        $pipeline = new MiddlewarePipeline();

        // Der Controller schickt den Request nun durch die HTTP-Methoden-Prüfung
        $pipeline->add(new HttpMethodMiddleware(['POST']));
        // Alle APIs brauchen CSRF
        $pipeline->add(new ApiCsrfMiddleware());

        if ($rateLimit) {
            $pipeline->add(new ApiRateLimitMiddleware($this->rateLimiter));
        }

        if ($permission !== null) {
            $pipeline->add(new ApiPermissionMiddleware($this->auth, $permission));
        }

        // 1. Payload Stream global und sicher abgreifen - ABER NUR WENN ES JSON IST!
        $inputData   = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (\in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true) && \str_contains($contentType, 'application/json')) {
            $raw = \file_get_contents('php://input');
            if ($raw !== '' && $raw !== false) {
                try {
                    $inputData = \json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    JsonResponse::error('Bad Request: Ungültiges JSON-Format gesendet.', 400);

                    return;
                }
            }
        }

        // 2. Request bündeln
        $requestData = [
            'get'   => $_GET,
            'post'  => $_POST,
            'input' => $inputData, // Hier liegen die entschlüsselten JSON-Werte (falls vorhanden)
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        // 3. Durch die Pipeline an die Action schicken
        $pipeline->process($requestData, function (array $req) use ($actionKey): void {
            $action = $this->factory->create($actionKey);
            if ($action !== null) {
                $action->execute($req);
            } else {
                JsonResponse::error("API Endpoint '$actionKey' not found.", 404);
            }
        });
    }
}
