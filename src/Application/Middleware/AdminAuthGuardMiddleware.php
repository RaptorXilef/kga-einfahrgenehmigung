<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\View\TemplateRenderer;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Schützt den AdminController und rendert bei Bedarf die Login-Ansicht.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class AdminAuthGuardMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function process(array $requestData, callable $next): mixed
    {
        try {
            if (! $this->auth->isLoggedIn()) {
                throw new \RuntimeException('Not logged in');
            }
        } catch (\RuntimeException) {
            // Zeigt die Login-Seite an und stoppt die Pipeline!
            $this->renderer->render('admin_login', [
                'auth'            => $this->auth,
                'groupRepository' => $this->groupRepository,
                'message'         => '',
                'userRepository'  => $this->userRepository,
            ]);

            return null;
        }

        return $next($requestData);
    }
}
