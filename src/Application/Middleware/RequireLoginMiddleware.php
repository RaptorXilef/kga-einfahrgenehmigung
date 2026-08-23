<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

final class RequireLoginMiddleware implements MiddlewareInterface
{
    private ?string $fallbackUrl = null;

    public function __construct(
        private readonly AuthService $auth,
        private readonly ?RoleRepositoryInterface $roleRepository = null,
        private readonly ?TemplateRenderer $renderer = null,
        private readonly ?UserRepositoryInterface $userRepository = null,
    ) {
    }

    public static function withRedirect(AuthService $auth, string $fallbackUrl): self
    {
        $middleware = new self($auth);
        $middleware->fallbackUrl = $fallbackUrl;

        return $middleware;
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        if (!$this->auth->isLoggedIn()) {
            if ($this->fallbackUrl !== null) {
                return new RedirectResponse($this->fallbackUrl);
            }

            if ($this->renderer instanceof TemplateRenderer
                && $this->roleRepository instanceof RoleRepositoryInterface
                && $this->userRepository instanceof UserRepositoryInterface
            ) {
                $this->renderer->render('admin_login', [
                    'auth' => $this->auth,
                    'roleRepository' => $this->roleRepository,
                    'userRepository' => $this->userRepository,
                ]);

                return null;
            }

            return new RedirectResponse('index.php');
        }

        return $next($request);
    }
}
