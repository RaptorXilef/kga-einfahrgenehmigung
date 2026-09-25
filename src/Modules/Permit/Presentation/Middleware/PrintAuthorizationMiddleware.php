<?php

declare(strict_types=1);

namespace App\Modules\Permit\Presentation\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\AuthorizationInterface;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use Override;

/**
 * Guard für die Druck-Berechtigung.
 */
final readonly class PrintAuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private SessionManager $sessionManager,
        private GetPermitByCodeHandler $getPermitByCodeHandler,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $code = (string) ($request->get['code'] ?? '');
        if ($code === '') {
            return $next($request);
        }

        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($code));

        if (!$permit instanceof PermitReadDto) {
            return $next($request);
        }

        if (!$this->auth->hasPermission('permits.print')) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung zum Drucken dieser Genehmigung.');

            return new RedirectResponse('admin');
        }

        return $next($request);
    }
}
