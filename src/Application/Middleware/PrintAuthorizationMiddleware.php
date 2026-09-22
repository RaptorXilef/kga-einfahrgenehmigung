<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Domain\Permit;

/**
 * Guard für die Druck-Berechtigung.
 */
final readonly class PrintAuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
        private GetPermitByCodeHandler $getPermitByCodeHandler, // CQRS
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        $code = (string) ($request->get['code'] ?? '');
        if ($code === '') {
            return $next($request);
        }

        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($code));

        if (!$permit instanceof Permit) {
            return $next($request);
        }

        if (!$this->auth->hasPermission('permits.print')) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung zum Drucken dieser Genehmigung.');

            return new RedirectResponse('admin');
        }

        return $next($request);
    }
}
