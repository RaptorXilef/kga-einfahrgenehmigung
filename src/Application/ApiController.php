<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\ApiActionFactory;
use App\Application\Middleware\ApiCsrfMiddleware;
use App\Application\Middleware\ApiPermissionMiddleware;
use App\Application\Middleware\ApiRateLimitMiddleware;
use App\Application\Middleware\CorsMiddleware;
use App\Application\Middleware\HttpMethodMiddleware;
use App\Application\Middleware\JsonBodyParserMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ResponseInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\AuthService;

/**
 * Zentraler Front-Controller für alle asynchronen (JSON & Form) API-Routen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
        $pipeline = new MiddlewarePipeline();

        // 1. CORS Pre-Flights abfangen
        $pipeline->add(new CorsMiddleware());

        // 2. HTTP-Methoden absichern
        $pipeline->add(new HttpMethodMiddleware(['POST']));

        // 3. CSRF-Schutz
        $pipeline->add(new ApiCsrfMiddleware());

        // 4. Rate-Limiting (falls gefordert)
        if ($rateLimit) {
            $pipeline->add(new ApiRateLimitMiddleware($this->rateLimiter));
        }

        // 5. Rechteprüfung (falls gefordert)
        if ($permission !== null) {
            $pipeline->add(new ApiPermissionMiddleware($this->auth, $permission));
        }

        // 6. JSON Body sicher parsen
        $pipeline->add(new JsonBodyParserMiddleware());

        // Basis-Request schnüren (wird von Middlewares angereichert)
        $requestData = [
            'get'   => $_GET,
            'post'  => $_POST,
            'input' => [], // Wird durch JsonBodyParserMiddleware gefüllt
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        // Wir fangen die Response jetzt sauber ab, statt sie in der Action zu killen!
        $response = $pipeline->process($requestData, function (array $req) use ($actionKey): mixed {
            $action = $this->factory->create($actionKey);
            if ($action !== null) {
                return $action->execute($req);
            }

            return JsonResponse::error("API Endpoint '$actionKey' not found.", 404);
        });

        if ($response instanceof ResponseInterface) {
            $response->send();
        }
    }
}
