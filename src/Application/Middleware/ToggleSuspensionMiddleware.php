<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Entity\PermitStatus;
use App\Core\Service\AuthService;

/**
 * Guard für das Sperren/Entsperren von Genehmigungen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ToggleSuspensionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        try {
            // Nutze DTO für die Validierung statt rohem Array-Zugriff!
            $dto  = SimpleIdentifierRequest::fromArray($request->post, 'code');
            $code = $dto->identifier;
        } catch (ValidationException) {
            // Wenn Code fehlt, durchlassen -> Die Action wirft dann den Fehler!
            return $next($request);
        }

        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return $next($request);
        }

        $isUnpaid = $permit->getStatus() !== PermitStatus::Bezahlt;
        $hasRight = false;

        if ($isUnpaid && $this->auth->hasPermission('dashboard.finance.suspend')) {
            $hasRight = true;
        } elseif (! $isUnpaid && $this->auth->hasPermission('dashboard.active.suspend')) {
            $hasRight = true;
        }

        if (! $hasRight) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung zum Sperren.');

            return new RedirectResponse('admin.php');
        }

        return $next($request);
    }
}
