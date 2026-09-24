<?php

declare(strict_types=1);

namespace App\Modules\Permit\Presentation\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Permit\Application\UseCases\TogglePermitSuspension\PermitToggleSuspensionRequest;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use Override;

/**
 * Guard für das Sperren/Entsperren von Genehmigungen.
 */
final readonly class ToggleSuspensionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
        private PermitRepositoryInterface $repository,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        try {
            $dto = PermitToggleSuspensionRequest::fromArray($request->post);
            $code = $dto->code;
        } catch (ValidationException) {
            // Wenn Code fehlt, durchlassen -> Die Action wirft dann den Fehler!
            return $next($request);
        }

        $permit = $this->repository->findByCode($code);
        if (!$permit instanceof Permit) {
            return $next($request);
        }

        if (!$this->auth->hasPermission('permits.suspend')) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung zum Sperren.');

            return new RedirectResponse('admin');
        }

        return $next($request);
    }
}
