<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\ApiCsrfMiddleware;
use App\Application\Middleware\ApiPermissionMiddleware;
use App\Application\Middleware\ApiRateLimitMiddleware;
use App\Application\Middleware\CorsMiddleware;
use App\Application\Middleware\HttpMethodMiddleware;
use App\Application\Middleware\JsonBodyParserMiddleware;
use App\Application\Middleware\MaintenanceGuardMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Response\JsonResponse;
use App\Application\Routing\UniversalActionFactory;
use App\Application\Session\SessionManager;
use App\Contracts\Application\RequiresPermissionInterface;
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
        private UniversalActionFactory $factory,
        private AuthService $auth,
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private SecurityHeadersMiddleware $securityHeaders,
        private MaintenanceGuardMiddleware $maintenanceGuard,
    ) {
    }

    public function handle(ServerRequest $request, string $actionKey, bool $rateLimit = false): void
    {
        $action = $this->factory->create($actionKey);

        $pipeline = new MiddlewarePipeline();
        $pipeline
            ->add($this->securityHeaders) // TODO Überarbeitung prüfen
            ->add($this->maintenanceGuard) // TODO Überarbeitung prüfen
            ->add(new CorsMiddleware())
            ->add(new HttpMethodMiddleware(['POST', 'GET']))
            ->add(new ApiCsrfMiddleware($this->sessionManager));

        if ($rateLimit) {
            $pipeline->add(new ApiRateLimitMiddleware($this->rateLimiter));
        }

        // Dynamisches Routing anhand des Interfaces
        if ($action instanceof RequiresPermissionInterface) {
            $pipeline->add(new ApiPermissionMiddleware($this->auth, $action->getRequiredPermission()));
        }

        // 6. JSON Body sicher parsen
        $pipeline->add(new JsonBodyParserMiddleware());

        $response = $pipeline->process($request, function (ServerRequest $req) use ($action, $actionKey): mixed {
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
