<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class RequireLoginMiddleware implements MiddlewareInterface
{
    private ?string $fallbackUrl = null;

    public function __construct(
        private readonly AuthService $auth,
        private readonly ?GroupRepositoryInterface $groupRepository = null,
        private readonly ?TemplateRenderer $renderer = null,
        private readonly ?UserRepositoryInterface $userRepository = null,
    ) {
    }

    /**
     * Factory-Methode für Controller, die bei fehlendem Login einen Redirect ausführen sollen.
     */
    public static function withRedirect(AuthService $auth, string $fallbackUrl): self
    {
        $middleware              = new self($auth);
        $middleware->fallbackUrl = $fallbackUrl;

        return $middleware;
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        if (! $this->auth->isLoggedIn()) {

            // Modus 1: Harter Redirect (z.B. für Profil- oder Changelog-Seite)
            if ($this->fallbackUrl !== null) {
                return new RedirectResponse($this->fallbackUrl);
            }

            // Modus 2: Rendern des Admin-Login-Formulars (Standard für das Dashboard)
            if ($this->renderer !== null && $this->groupRepository !== null && $this->userRepository !== null) {
                $this->renderer->render('admin_login', [
                    'auth'            => $this->auth,
                    'groupRepository' => $this->groupRepository,
                    'userRepository'  => $this->userRepository,
                ]);

                return null;
            }

            return new RedirectResponse('index.php'); // Fallback
        }

        return $next($request);
    }
}
